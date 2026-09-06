<?php

namespace App\Actions\Purchasing;

use App\Enums\Purchasing\PurchaseOrderStatus;
use App\Models\Purchasing\PurchaseOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CancelPurchaseOrder
{
    public function handle(PurchaseOrder $purchaseOrder, string $reason, ?int $userId = null): PurchaseOrder
    {
        if ($purchaseOrder->isCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'La orden de compra ya se encuentra cancelada.',
            ]);
        }

        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'El motivo de cancelación es obligatorio.',
            ]);
        }

        $purchaseOrder->update([
            'status' => PurchaseOrderStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $userId ?? auth()->id(),
            'cancellation_reason' => $trimmedReason,
        ]);

        Log::info(sprintf(
            'Purchase order cancelled [ID: %d, Number: %s, Reason: %s] by User ID: %s',
            $purchaseOrder->id,
            $purchaseOrder->order_number,
            $trimmedReason,
            $userId ?? auth()->id() ?? 'system'
        ));

        return $purchaseOrder;
    }
}
