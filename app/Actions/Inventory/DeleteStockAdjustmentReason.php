<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\StockAdjustmentReason;
use Illuminate\Validation\ValidationException;

class DeleteStockAdjustmentReason
{
    /**
     * Delete a stock adjustment reason, ensuring it has not been used in recorded operations.
     *
     * @throws ValidationException
     */
    public function handle(StockAdjustmentReason $reason): void
    {
        if ($reason->isInUse()) {
            throw ValidationException::withMessages([
                'reason' => 'No se puede dar de baja un motivo de ajuste que ya ha sido utilizado en operaciones registradas.',
            ]);
        }

        $reason->delete();
    }
}
