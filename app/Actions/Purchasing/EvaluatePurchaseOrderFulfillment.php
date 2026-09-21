<?php

namespace App\Actions\Purchasing;

use App\Enums\Purchasing\PurchaseOrderStatus;
use App\Models\Purchasing\PurchaseOrder;
use Illuminate\Support\Facades\Log;

class EvaluatePurchaseOrderFulfillment
{
    public function handle(PurchaseOrder|int $order): PurchaseOrder
    {
        $purchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail(
            $order instanceof PurchaseOrder ? $order->id : $order
        );

        if ($purchaseOrder->isCancelled() || $purchaseOrder->isDraft()) {
            return $purchaseOrder;
        }

        $isFullyReceived = $purchaseOrder->isFullyReceived();

        if ($purchaseOrder->isIssued() && $isFullyReceived) {
            $purchaseOrder->update(['status' => PurchaseOrderStatus::Fulfilled]);
        } elseif ($purchaseOrder->isFulfilled() && ! $isFullyReceived) {
            $purchaseOrder->update(['status' => PurchaseOrderStatus::Issued]);
        } else {
            return $purchaseOrder;
        }

        Log::info('Purchase order fulfillment status changed', [
            'purchase_order_id' => $purchaseOrder->id,
            'status' => $purchaseOrder->status->value,
            'user_id' => auth()->id(),
        ]);

        return $purchaseOrder;
    }

    /** @param array<int, int> $orderIds */
    public function handleMany(array $orderIds): void
    {
        $orderIds = array_unique($orderIds);
        sort($orderIds);

        foreach ($orderIds as $orderId) {
            $this->handle($orderId);
        }
    }
}
