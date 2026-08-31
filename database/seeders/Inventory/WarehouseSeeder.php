<?php

namespace Database\Seeders\Inventory;

use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $centro = Branch::where('name', 'Sucursal Centro')->firstOrFail();
        $norte = Branch::where('name', 'Sucursal Norte')->firstOrFail();

        $warehouses = [
            [
                'name' => 'Depósito Central',
                'branch_id' => $centro->id,
                'is_online_channel' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Depósito Norte',
                'branch_id' => $norte->id,
                'is_online_channel' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Depósito E-commerce',
                'branch_id' => $centro->id,
                'is_online_channel' => true,
                'is_active' => true,
            ],
        ];

        foreach ($warehouses as $data) {
            Warehouse::updateOrCreate(
                ['name' => $data['name'], 'branch_id' => $data['branch_id']],
                $data
            );
        }
    }
}
