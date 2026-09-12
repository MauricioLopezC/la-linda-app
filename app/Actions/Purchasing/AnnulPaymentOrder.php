<?php

namespace App\Actions\Purchasing;

use App\Enums\Purchasing\PaymentOrderStatus;
use App\Models\Purchasing\PaymentOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Annul a payment order.
 *
 * This marks the payment order as Cancelled and triggers the recalculation of
 * pending balances for all supplier vouchers that were involved, effectively
 * releasing the locked amounts (invoices go back to unpaid, NCs go back to unapplied).
 */
class AnnulPaymentOrder
{
    public function __construct(
        private readonly RecalculateVoucherBalanceStatus $recalculateStatus,
    ) {}

    public function handle(PaymentOrder $order, ?int $userId = null): void
    {
        if ($order->status === PaymentOrderStatus::Cancelled) {
            throw ValidationException::withMessages([
                'order' => 'La orden de pago ya se encuentra anulada.',
            ]);
        }

        DB::transaction(function () use ($order, $userId): void {
            // Lock the order for update just in case
            $order->lockForUpdate()->find($order->id);

            $order->status = PaymentOrderStatus::Cancelled;
            $order->save();

            // Load the items to recalculate the balance for the related vouchers
            $order->loadMissing('items.voucher');

            foreach ($order->items as $item) {
                // The balance logic excludes Cancelled payment orders (see SupplierVoucher aggregates)
                // so simply calling recalculateStatus will read the new balance properly.
                $this->recalculateStatus->handle($item->voucher);
            }

            Log::info(sprintf(
                'Payment order annulled [ID: %d, Number: %s] by User ID: %s',
                $order->id,
                $order->order_number,
                $userId ?? auth()->id() ?? 'system',
            ));
        });
    }
}
