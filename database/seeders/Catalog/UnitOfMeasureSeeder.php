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
            ['name' => 'Unidad', 'abbreviation' => 'u'],
            ['name' => 'Kilogramo', 'abbreviation' => 'kg'],
            ['name' => 'Litro', 'abbreviation' => 'l'],
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
