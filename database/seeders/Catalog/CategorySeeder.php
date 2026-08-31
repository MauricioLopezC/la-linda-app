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
            'Almacén' => [
                'Conservas',
                'Harinas y Legumbres',
                'Arroz y Pastas',
                'Aceites y Aderezos',
                'Azúcares y Endulzantes',
            ],
            'Bebidas' => [
                'Gaseosas',
                'Aguas y Saborizadas',
                'Cervezas',
                'Jugos',
            ],
            'Lácteos y Frescos' => [
                'Leches',
                'Yogures',
                'Quesos',
                'Mantecas y Cremas',
            ],
            'Infusiones y Desayuno' => [
                'Yerba Mate',
                'Café y Té',
                'Galletitas y Snacks',
            ],
            'Limpieza' => [
                'Cuidado de la Ropa',
                'Lavandinas y Desinfectantes',
                'Lavavajillas',
            ],
            'Perfumería y Cuidado Personal' => [
                'Higiene Bucal',
                'Jabones y Champús',
                'Desodorantes',
            ],
            'Congelados' => [
                'Verduras Congeladas',
                'Hamburguesas y Rebozados',
            ],
            'Panadería y Confitería' => [
                'Panificados',
                'Repostería',
            ],
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
