<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\StockAdjustmentReason;

class CreateStockAdjustmentReason
{
    /**
     * Create a new stock adjustment reason.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): StockAdjustmentReason
    {
        return StockAdjustmentReason::create([
            'name' => (string) $data['name'],
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
        ]);
    }
}
