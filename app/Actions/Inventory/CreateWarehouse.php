<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\Warehouse;

class CreateWarehouse
{
    /**
     * Create a new warehouse.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Warehouse
    {
        return Warehouse::create([
            'name' => (string) $data['name'],
            'branch_id' => (int) $data['branch_id'],
            'is_online_channel' => isset($data['is_online_channel']) ? (bool) $data['is_online_channel'] : false,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
        ]);
    }
}
