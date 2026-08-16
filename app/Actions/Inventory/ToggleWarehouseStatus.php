<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\Warehouse;
use Illuminate\Validation\ValidationException;

class ToggleWarehouseStatus
{
    /**
     * Toggle the active status of a warehouse, blocking deactivation when it has
     * registered stock or movements.
     *
     * @throws ValidationException
     */
    public function handle(Warehouse $warehouse): Warehouse
    {
        if ($warehouse->is_active && $warehouse->hasRegisteredStock()) {
            throw ValidationException::withMessages([
                'warehouse' => 'No se puede dar de baja un depósito con existencias o movimientos registrados.',
            ]);
        }

        $warehouse->update([
            'is_active' => ! $warehouse->is_active,
        ]);

        return $warehouse;
    }
}
