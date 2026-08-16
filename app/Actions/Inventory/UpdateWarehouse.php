<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\Warehouse;

class UpdateWarehouse
{
    /**
     * Update an existing warehouse.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Warehouse $warehouse, array $data): Warehouse
    {
        $warehouse->update([
            'name' => (string) $data['name'],
            'branch_id' => (int) $data['branch_id'],
            'is_online_channel' => isset($data['is_online_channel']) ? (bool) $data['is_online_channel'] : $warehouse->is_online_channel,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $warehouse->is_active,
        ]);

        return $warehouse;
    }
}
