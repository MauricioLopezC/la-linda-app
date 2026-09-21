<?php

namespace App\Actions\Purchasing;

use App\Enums\Purchasing\PurchaseOrderStatus;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Models\Purchasing\PurchaseOrderVoucherImputation;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\SupplierVoucherItem;
use Illuminate\Validation\ValidationException;

class ImputeSupplierVoucherToPurchaseOrders
{
    /**
     * Imputes the lines of a supplier voucher to their matching purchase order items.
     *
     * @param  array<int, array{item: SupplierVoucherItem, purchase_order_item_id: int}>  $imputationsData
     */
    public function handle(SupplierVoucher $voucher, array $imputationsData): void
    {
        if (empty($imputationsData)) {
            return;
        }

        $affectedOrders = [];

        foreach ($imputationsData as $data) {
            /** @var SupplierVoucherItem $voucherItem */
            $voucherItem = $data['item'];
            $poItemId = $data['purchase_order_item_id'];

            /** @var PurchaseOrderItem|null $poItem */
            $poItem = PurchaseOrderItem::query()
                ->with(['purchaseOrder', 'article'])
                ->lockForUpdate()
                ->find($poItemId);

            if ($poItem === null) {
                throw ValidationException::withMessages([
                    'items' => 'El renglón de la orden de compra no existe.',
                ]);
            }

            $order = $poItem->purchaseOrder;

            if ($order->supplier_id !== $voucher->supplier_id) {
                throw ValidationException::withMessages([
                    'items' => "La orden de compra #{$order->order_number} no pertenece al proveedor seleccionado.",
                ]);
            }

            if (! $order->isIssued()) {
                throw ValidationException::withMessages([
                    'items' => "La orden de compra #{$order->order_number} no se encuentra en estado emitida.",
                ]);
            }

            if ($voucherItem->article_id !== null && $voucherItem->article_id !== $poItem->article_id) {
                throw ValidationException::withMessages([
                    'items' => "El artículo del comprobante no coincide con el artículo solicitado en la orden #{$order->order_number}.",
                ]);
            }

            $receivedQty = (float) $voucherItem->quantity;
            $pendingQty = (float) $poItem->quantityPending();

            if ($pendingQty <= 0.0001) {
                throw ValidationException::withMessages([
                    'items' => "El renglón del artículo {$poItem->article->description} en la orden #{$order->order_number} ya se encuentra cubierto en su totalidad.",
                ]);
            }

            // Desglose: lo aplicado para saldar la OC y el excedente físico aceptado (pesables / carnicería)
            $appliedQty = min($receivedQty, $pendingQty);
            $excessQty = max(0.0, $receivedQty - $pendingQty);

            PurchaseOrderVoucherImputation::create([
                'purchase_order_item_id' => $poItem->id,
                'supplier_voucher_item_id' => $voucherItem->id,
                'quantity_received' => number_format($appliedQty, 3, '.', ''),
                'quantity_excess' => number_format($excessQty, 3, '.', ''),
            ]);

            $affectedOrders[$order->id] = $order;
        }

        // Evaluar cumplimiento de las órdenes de compra afectadas (HU-038 preparation)
        $ordersToEvaluate = PurchaseOrder::query()
            ->whereIn('id', array_keys($affectedOrders))
            ->with('items.imputations')
            ->get();

        foreach ($ordersToEvaluate as $order) {
            if ($order->isFullyReceived()) {
                $order->update(['status' => PurchaseOrderStatus::Fulfilled]);
            }
        }
    }
}
