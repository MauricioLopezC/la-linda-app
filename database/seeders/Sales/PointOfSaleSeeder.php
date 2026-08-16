<?php

namespace Database\Seeders\Sales;

use App\Models\Inventory\Warehouse;
use App\Models\Sales\PointOfSale;
use Illuminate\Database\Seeder;

class PointOfSaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $depositoCentral = Warehouse::where('name', 'Depósito Central')->firstOrFail();
        $depositoNorte = Warehouse::where('name', 'Depósito Norte')->firstOrFail();

        $pointsOfSale = [
            [
                'number' => 1,
                'warehouse_id' => $depositoCentral->id,
                'is_active' => true,
            ],
            [
                'number' => 1,
                'warehouse_id' => $depositoNorte->id,
                'is_active' => true,
            ],
        ];

        foreach ($pointsOfSale as $data) {
            PointOfSale::firstOrCreate(['number' => $data['number'], 'warehouse_id' => $data['warehouse_id']], $data);
        }
    }
}
