<?php

namespace Database\Seeders\Catalog;

use App\Models\Catalog\UnitOfMeasure;
use Illuminate\Database\Seeder;

class UnitOfMeasureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $unitsOfMeasure = [
            ['name' => 'Unidad', 'abbreviation' => 'u', 'allows_decimal_quantity' => false],
            ['name' => 'Kilogramo', 'abbreviation' => 'kg', 'allows_decimal_quantity' => true],
            ['name' => 'Litro', 'abbreviation' => 'l', 'allows_decimal_quantity' => true],
        ];

        UnitOfMeasure::unguarded(function () use ($unitsOfMeasure): void {
            foreach ($unitsOfMeasure as $data) {
                UnitOfMeasure::firstOrCreate(
                    ['name_normalized' => UnitOfMeasure::normalizeUniqueValue($data['name'])],
                    [
                        ...$data,
                        'abbreviation_normalized' => UnitOfMeasure::normalizeUniqueValue($data['abbreviation']),
                        'is_active' => true,
                    ],
                );
            }
        });
    }
}
