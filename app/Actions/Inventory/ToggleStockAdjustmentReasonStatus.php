<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\StockAdjustmentReason;

class ToggleStockAdjustmentReasonStatus
{
    /**
     * Toggle the active status of a stock adjustment reason.
     */
    public function handle(StockAdjustmentReason $reason): StockAdjustmentReason
    {
        $reason->update([
            'is_active' => ! $reason->is_active,
        ]);

        return $reason;
    }
}
