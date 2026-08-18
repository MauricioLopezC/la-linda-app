<?php

namespace Database\Seeders\Catalog;

use App\Models\Catalog\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Almacén' => ['Conservas'],
            'Bebidas' => ['Gaseosas'],
        ];

        Category::unguarded(function () use ($categories): void {
            foreach ($categories as $rootName => $children) {
                $root = Category::firstOrCreate(
                    [
                        'scope_key' => 0,
                        'name_normalized' => Category::normalizeUniqueValue($rootName),
                    ],
                    [
                        'name' => $rootName,
                        'parent_id' => null,
                        'is_active' => true,
                    ],
                );

                foreach ($children as $childName) {
                    Category::firstOrCreate(
                        [
                            'scope_key' => $root->id,
                            'name_normalized' => Category::normalizeUniqueValue($childName),
                        ],
                        [
                            'name' => $childName,
                            'parent_id' => $root->id,
                            'is_active' => true,
                        ],
                    );
                }
            }
        });
    }
}
