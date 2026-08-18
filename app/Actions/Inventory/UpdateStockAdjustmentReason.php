<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\StockAdjustmentReason;

class UpdateStockAdjustmentReason
{
    /**
     * Update an existing stock adjustment reason.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(StockAdjustmentReason $reason, array $data): StockAdjustmentReason
    {
        $reason->update([
            'name' => (string) $data['name'],
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $reason->is_active,
        ]);

        return $reason;
    }
}
