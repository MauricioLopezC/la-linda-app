<?php

namespace Database\Seeders\Catalog;

use App\Models\Catalog\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Brand::unguarded(function (): void {
            $brands = [
                'Arcor',
                'La Serenísima',
                'Coca-Cola',
                'Quilmes',
                'Playadito',
                'Pureza',
                'Gallo',
                'Levité',
                'Villavicencio',
                'Marolio',
                'Lucchetti',
                'Natura',
                'Terrabusi',
                'Ledesma',
            ];

            foreach ($brands as $name) {
                Brand::firstOrCreate(
                    ['name_normalized' => Brand::normalizeUniqueValue($name)],
                    ['name' => $name, 'is_active' => true],
                );
            }
        });
    }
}
