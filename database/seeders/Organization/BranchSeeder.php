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
                'address' => 'Av. San Martín 1250, Salta Capital',
                'phone' => '387-4001100',
                'is_active' => true,
            ],
            [
                'name' => 'Sucursal Norte',
                'address' => 'Belgrano 430, General Güemes',
                'phone' => '387-4002200',
                'is_active' => true,
            ],
        ];

        foreach ($branches as $data) {
            Branch::firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
