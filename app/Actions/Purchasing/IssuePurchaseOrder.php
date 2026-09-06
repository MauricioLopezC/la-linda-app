<?php

namespace App\Actions\Purchasing;

use App\Enums\Purchasing\PurchaseOrderStatus;
use App\Models\Purchasing\PurchaseOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class IssuePurchaseOrder
{
    public function handle(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if (! $purchaseOrder->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden emitir órdenes de compra en estado borrador.',
            ]);
        }

        if ($purchaseOrder->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => 'No se puede emitir una orden de compra sin al menos un artículo.',
            ]);
        }

        if (! $purchaseOrder->supplier->is_active) {
            throw ValidationException::withMessages([
                'supplier_id' => 'El proveedor de la orden no se encuentra activo.',
            ]);
        }

        if (! $purchaseOrder->warehouse->is_active) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'El depósito de la orden no se encuentra activo.',
            ]);
        }

        $purchaseOrder->update([
            'status' => PurchaseOrderStatus::Issued,
        ]);

        Log::info(sprintf(
            'Purchase order issued [ID: %d, Number: %s, Total: %s] by User ID: %s',
            $purchaseOrder->id,
            $purchaseOrder->order_number,
            $purchaseOrder->total_amount,
            auth()->id() ?? 'system'
        ));

        return $purchaseOrder;
    }
}
