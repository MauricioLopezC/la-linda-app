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

        if (! $warehouse->is_active && $warehouse->is_online_channel) {
            $anotherActiveOnlineExists = Warehouse::query()
                ->where('is_online_channel', true)
                ->where('is_active', true)
                ->where('id', '!=', $warehouse->id)
                ->exists();

            if ($anotherActiveOnlineExists) {
                throw ValidationException::withMessages([
                    'warehouse' => 'Ya existe un depósito activo asignado al canal online.',
                ]);
            }
        }

        $warehouse->update([
            'is_active' => ! $warehouse->is_active,
        ]);

        return $warehouse;
    }
}
