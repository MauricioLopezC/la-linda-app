<?php

namespace App\Actions\Purchasing;

use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use Illuminate\Validation\ValidationException;

class ResolveRemitoWarehouse
{
    /**
     * @param  array<int, array{purchase_order_item_id?: ?int}>  $items
     */
    public function handle(
        SupplierVoucherType $type,
        ?int $submittedWarehouseId,
        array $items
    ): ?int {
        if (! $type->isRemito()) {
            if ($submittedWarehouseId !== null) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Solo los remitos pueden tener un depósito asignado.',
                ]);
            }

            return null;
        }

        $poItemIds = collect($items)
            ->pluck('purchase_order_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($poItemIds->isEmpty()) {
            if ($submittedWarehouseId === null) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'El depósito es obligatorio para un remito libre.',
                ]);
            }

            $warehouse = Warehouse::query()
                ->active()
                ->lockForUpdate()
                ->find($submittedWarehouseId);

            if ($warehouse === null) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'El depósito seleccionado no existe o no se encuentra activo.',
                ]);
            }

            return $warehouse->id;
        }

        // Remito imputado a OCs
        $poItems = PurchaseOrderItem::query()
            ->whereIn('id', $poItemIds)
            ->lockForUpdate()
            ->get();

        $orderIds = $poItems->pluck('purchase_order_id')->unique()->values();
        $orders = PurchaseOrder::query()
            ->whereIn('id', $orderIds)
            ->lockForUpdate()
            ->get();

        $distinctWarehouseIds = $orders->pluck('warehouse_id')->unique()->values();

        if ($distinctWarehouseIds->count() > 1) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Las órdenes de compra seleccionadas pertenecen a depósitos distintos. Debe registrar remitos separados por depósito.',
            ]);
        }

        $derivedWarehouseId = (int) $distinctWarehouseIds->first();

        if ($submittedWarehouseId !== null && (int) $submittedWarehouseId !== $derivedWarehouseId) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'El depósito seleccionado no coincide con el depósito de la orden de compra.',
            ]);
        }

        $warehouse = Warehouse::query()
            ->active()
            ->lockForUpdate()
            ->find($derivedWarehouseId);

        if ($warehouse === null) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'El depósito de la orden de compra no se encuentra activo.',
            ]);
        }

        return $warehouse->id;
    }
}
