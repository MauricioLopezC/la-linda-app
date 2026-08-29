<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\StockMovementType;
use Illuminate\Validation\ValidationException;

class DeleteStockMovementType
{
    /**
     * Delete a stock movement type ensuring system protection and referential integrity.
     *
     * @throws ValidationException
     */
    public function handle(StockMovementType $movementType): void
    {
        if ($movementType->is_system) {
            throw ValidationException::withMessages([
                'movement_type' => 'Los tipos de movimiento propios del sistema no pueden eliminarse.',
            ]);
        }

        if ($movementType->isInUse()) {
            throw ValidationException::withMessages([
                'movement_type' => 'No se puede dar de baja un tipo de movimiento que ya ha sido utilizado en operaciones registradas.',
            ]);
        }

        $movementType->delete();
    }
}
