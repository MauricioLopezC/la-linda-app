<?php

namespace App\Actions\Sales;

use App\Models\Sales\PointOfSale;

class CreatePointOfSale
{
    /**
     * Create a new point of sale.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): PointOfSale
    {
        return PointOfSale::create([
            'number' => (int) $data['number'],
            'warehouse_id' => (int) $data['warehouse_id'],
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
        ]);
    }
}
