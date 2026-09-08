<?php

namespace App\Actions\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Data\Purchasing\PaymentOrderData;
use App\Data\Purchasing\PaymentOrderItemData;
use App\Enums\Purchasing\PaymentOrderStatus;
use App\Models\Purchasing\PaymentOrder;
use App\Models\Purchasing\PaymentOrderItem;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Sales\PaymentMethod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Emit a payment order, imputing amounts to one or more supplier invoices.
 *
 * Shared balance engine: uses the same pendingBalance() / RecalculateVoucherBalanceStatus
 * as HU-054 (credit/debit note applications). All balance arithmetic is performed in integer
 * cents via ConvertsMoneyToCents to avoid floating-point errors.
 *
 * Concurrency: wraps everything in a transaction and locks all touched invoices with
 * lockForUpdate() so two concurrent orders cannot both succeed against the same balance.
 */
class IssuePaymentOrder
{
    use ConvertsMoneyToCents;

    public function __construct(
        private readonly RecalculateVoucherBalanceStatus $recalculateStatus,
    ) {}

    /**
     * @param  array{
     *     supplier_id: int,
     *     payment_method_id: int,
     *     date: string,
     *     notes: ?string,
     *     items: array<int, array{supplier_voucher_id: int, amount_applied: string}>
     * }  $data
     */
    public function handle(array $data, ?int $userId = null): PaymentOrderData
    {
        return DB::transaction(function () use ($data, $userId): PaymentOrderData {
            $supplier = Supplier::findOrFail((int) $data['supplier_id']);
            if (! $supplier->is_active) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'El proveedor seleccionado no está activo.',
                ]);
            }

            PaymentMethod::findOrFail((int) $data['payment_method_id']);

            $itemsData = $data['items'];

            // Collect voucher IDs and lock them for the duration of the transaction to
            // prevent concurrent orders from double-spending the same balance (Caso F).
            $voucherIds = array_map(
                fn (array $item): int => (int) $item['supplier_voucher_id'],
                $itemsData,
            );

            /** @var Collection<int, SupplierVoucher> $vouchers */
            $vouchers = SupplierVoucher::whereIn('id', $voucherIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $totalCents = 0;

            foreach ($itemsData as $index => $itemData) {
                $voucherId = (int) $itemData['supplier_voucher_id'];
                $amountApplied = (string) $itemData['amount_applied'];

                /** @var SupplierVoucher|null $voucher */
                $voucher = $vouchers->get($voucherId);

                if (! $voucher) {
                    throw ValidationException::withMessages([
                        "items.{$index}.supplier_voucher_id" => 'El comprobante seleccionado no existe.',
                    ]);
                }

                // Only invoices can be targets of payment orders (Caso C type check).
                if (! $voucher->type->isInvoice()) {
                    throw ValidationException::withMessages([
                        "items.{$index}.supplier_voucher_id" => 'Solo se pueden imputar facturas en una orden de pago. Las notas de crédito/débito no son válidas como destino.',
                    ]);
                }

                // Invoice must belong to the selected supplier (Caso C).
                if ($voucher->supplier_id !== $supplier->id) {
                    throw ValidationException::withMessages([
                        "items.{$index}.supplier_voucher_id" => 'El comprobante seleccionado no pertenece al proveedor indicado.',
                    ]);
                }

                // Amount applied must not exceed the invoice's pending balance (Caso B / F).
                $pendingCents = $this->moneyToCents($voucher->pendingBalance());
                $appliedCents = $this->moneyToCents($amountApplied);

                if ($appliedCents > $pendingCents) {
                    throw ValidationException::withMessages([
                        "items.{$index}.amount_applied" => 'El importe imputado supera el saldo pendiente del comprobante.',
                    ]);
                }

                $totalCents += $appliedCents;
            }

            $orderNumber = $this->generateNextOrderNumber();

            $order = PaymentOrder::create([
                'supplier_id' => $supplier->id,
                'payment_method_id' => (int) $data['payment_method_id'],
                'order_number' => $orderNumber,
                'date' => $data['date'],
                'total_amount' => $this->centsToMoney($totalCents),
                'status' => PaymentOrderStatus::Issued,
                'notes' => $data['notes'] ?? null,
                'user_id' => $userId ?? auth()->id(),
            ]);

            // Collect items and updated vouchers to build the response after the loop.
            /** @var array<int, array{item: PaymentOrderItem, voucher: SupplierVoucher}> $createdItems */
            $createdItems = [];

            foreach ($itemsData as $itemData) {
                $voucherId = (int) $itemData['supplier_voucher_id'];

                $item = PaymentOrderItem::create([
                    'payment_order_id' => $order->id,
                    'supplier_voucher_id' => $voucherId,
                    'amount_applied' => (string) $itemData['amount_applied'],
                ]);

                /** @var SupplierVoucher $voucher */
                $voucher = $vouchers->get($voucherId);

                // Reload from DB to pick up the new payment_order_items row before recalculating.
                $voucher = $voucher->fresh() ?? $voucher;
                $updatedVoucher = $this->recalculateStatus->handle($voucher);

                $createdItems[] = ['item' => $item, 'voucher' => $updatedVoucher];
            }

            $order->loadMissing(['supplier', 'paymentMethod']);

            Log::info(sprintf(
                'Payment order issued [ID: %d, Number: %s, Total: %s] by User ID: %s',
                $order->id,
                $order->order_number,
                $order->total_amount,
                $userId ?? auth()->id() ?? 'system',
            ));

            $itemDataObjects = array_map(
                fn (array $pair): PaymentOrderItemData => PaymentOrderItemData::fromModels(
                    $pair['item'],
                    $pair['voucher'],
                ),
                $createdItems,
            );

            return PaymentOrderData::fromModel($order, ...$itemDataObjects);
        });
    }

    /**
     * Generate the next global order number in the format OP-XXXXXX.
     *
     * Strategy: global correlative, analogous to CreatePurchaseOrder::generateNextOrderNumber()
     * which uses OC-XXXXXX. The UNIQUE constraint on order_number (added by the HU-027 migration)
     * is the final safety net; the loop handles the rare race where two processes pick the same
     * candidate simultaneously.
     */
    private function generateNextOrderNumber(): string
    {
        $maxId = PaymentOrder::max('id') ?? 0;
        $next = $maxId + 1;
        $orderNumber = sprintf('OP-%06d', $next);

        while (PaymentOrder::where('order_number', $orderNumber)->exists()) {
            $next++;
            $orderNumber = sprintf('OP-%06d', $next);
        }

        return $orderNumber;
    }
}
