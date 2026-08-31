<?php

namespace Database\Seeders\Organization;

use App\Models\Organization\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            [
                'name' => 'Sucursal Centro',
                'address' => 'Av. San Martín 1250, Salta Capital, Salta',
                'phone' => '+54 387 421-1100',
                'is_active' => true,
            ],
            [
                'name' => 'Sucursal Norte',
                'address' => 'Av. Belgrano 430, General Güemes, Salta',
                'phone' => '+54 387 400-2200',
                'is_active' => true,
            ],
        ];

        foreach ($branches as $data) {
            Branch::updateOrCreate(
                ['name' => $data['name']],
                $data
            );
        }
    }
}
