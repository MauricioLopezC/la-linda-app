<?php

namespace App\Actions\Sales;

use App\Models\Sales\PointOfSale;

class UpdatePointOfSale
{
    /**
     * Update an existing point of sale.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(PointOfSale $pointOfSale, array $data): PointOfSale
    {
        $pointOfSale->update([
            'number' => (int) $data['number'],
            'warehouse_id' => (int) $data['warehouse_id'],
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $pointOfSale->is_active,
        ]);

        return $pointOfSale;
    }
}
