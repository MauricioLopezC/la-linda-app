<?php

namespace Database\Seeders\Catalog;

use App\Enums\Catalog\ArticleStatus;
use App\Models\Catalog\Article;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\UnitOfMeasure;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::query()->get()->keyBy('name');
        $brands = Brand::query()->get()->keyBy('name');
        $unitsOfMeasure = UnitOfMeasure::query()->get()->keyBy('name');

        $articles = [
            [
                'description' => 'Duraznos en Almíbar en Mitades Arcor Lata 820 g',
                'internal_code' => 'ART-0001',
                'category' => 'Conservas',
                'brand' => 'Arcor',
                'unit' => 'Unidad',
                'barcode' => '7790580123456',
                'is_online_publishable' => true,
            ],
            [
                'description' => 'Choclo Amarillo en Grano Entero Arcor Lata 300 g',
                'internal_code' => 'ART-0002',
                'category' => 'Conservas',
                'brand' => 'Arcor',
                'unit' => 'Unidad',
                'barcode' => '7790580654321',
                'is_online_publishable' => true,
            ],
            [
                'description' => 'Harina de Trigo 000 Ultrarefinada Pureza Paquete 1 kg',
                'internal_code' => 'ART-0003',
                'category' => 'Harinas y Legumbres',
                'brand' => 'Pureza',
                'unit' => 'Kilogramo',
                'barcode' => '7791234567890',
                'is_online_publishable' => true,
            ],
            [
                'description' => 'Arroz Largo Fino Tipo 00000 Gallo Bolsa 1 kg',
                'internal_code' => 'ART-0004',
                'category' => 'Arroz y Pastas',
                'brand' => 'Gallo',
                'unit' => 'Kilogramo',
                'barcode' => '7792345678901',
                'is_online_publishable' => true,
            ],
            [
                'description' => 'Gaseosa Sabor Cola Clásica Coca-Cola Botella 1.5 L',
                'internal_code' => 'ART-0005',
                'category' => 'Gaseosas',
                'brand' => 'Coca-Cola',
                'unit' => 'Unidad',
                'barcode' => '7790895000017',
                'is_online_publishable' => true,
            ],
            [
                'description' => 'Agua Saborizada Pomelo Sin Gas Levité Botella 1.5 L',
                'internal_code' => 'ART-0006',
                'category' => 'Aguas y Saborizadas',
                'brand' => 'Levité',
                'unit' => 'Unidad',
                'barcode' => '7793456789012',
                'is_online_publishable' => true,
            ],
            [
                'description' => 'Agua Mineral Natural de Manantial Sin Gas Villavicencio Botella 2 L',
                'internal_code' => 'ART-0007',
                'category' => 'Aguas y Saborizadas',
                'brand' => 'Villavicencio',
                'unit' => 'Unidad',
                'barcode' => '7794567890123',
                'is_online_publishable' => true,
            ],
            [
                'description' => 'Leche Entera Homogeneizada 3% Grasa La Serenísima Tetra Brik 1 L',
                'internal_code' => 'ART-0008',
                'category' => 'Leches',
                'brand' => 'La Serenísima',
                'unit' => 'Litro',
                'barcode' => '7790742123456',
                'is_online_publishable' => true,
            ],
            [
                'description' => 'Yerba Mate Tradicional Elaborada con Palo Playadito Paquete 1 kg',
                'internal_code' => 'ART-0009',
                'category' => 'Yerba Mate',
                'brand' => 'Playadito',
                'unit' => 'Kilogramo',
                'barcode' => '7795678901234',
                'status' => ArticleStatus::Discontinued,
                'is_online_publishable' => false,
            ],
            [
                'description' => 'Aceite de Girasol Puro 100% Natura Botella 900 ml',
                'internal_code' => 'ART-0010',
                'category' => 'Aceites y Aderezos',
                'brand' => 'Natura',
                'unit' => 'Unidad',
                'barcode' => '7796789012345',
                'is_online_publishable' => true,
            ],
            [
                'description' => 'Fideos Secos Spaghetti Guisero Lucchetti Paquete 500 g',
                'internal_code' => 'ART-0011',
                'category' => 'Arroz y Pastas',
                'brand' => 'Lucchetti',
                'unit' => 'Unidad',
                'barcode' => '7797890123456',
                'is_online_publishable' => true,
            ],
            [
                'description' => 'Cerveza Rubia Clásica Lager Quilmes Botella 1 L',
                'internal_code' => 'ART-0012',
                'category' => 'Cervezas',
                'brand' => 'Quilmes',
                'unit' => 'Unidad',
                'barcode' => '7798901234567',
                'is_online_publishable' => true,
            ],
            [
                'description' => 'Tomate Pelado Entero en Jugo Marolio Lata 400 g',
                'internal_code' => 'ART-0013',
                'category' => 'Conservas',
                'brand' => 'Marolio',
                'unit' => 'Unidad',
                'barcode' => '7799012345678',
                'is_online_publishable' => true,
            ],
            [
                'description' => 'Azúcar Común Tipo A Ledesma Paquete 1 kg',
                'internal_code' => 'ART-0014',
                'category' => 'Azúcares y Endulzantes',
                'brand' => 'Ledesma',
                'unit' => 'Kilogramo',
                'barcode' => '7790123456789',
                'is_online_publishable' => true,
            ],
            [
                'description' => 'Galletitas Dulces Sabor Vainilla Terrabusi Paquete 300 g',
                'internal_code' => 'ART-0015',
                'category' => 'Galletitas y Snacks',
                'brand' => 'Terrabusi',
                'unit' => 'Unidad',
                'barcode' => '7791234509876',
                'is_online_publishable' => true,
            ],
        ];

        Article::unguarded(function () use ($articles, $categories, $brands, $unitsOfMeasure): void {
            foreach ($articles as $data) {
                Article::updateOrCreate(
                    ['internal_code_normalized' => Article::normalizeUniqueValue($data['internal_code'])],
                    [
                        'description' => $data['description'],
                        'internal_code' => $data['internal_code'],
                        'barcode' => $data['barcode'],
                        'category_id' => $categories[$data['category']]->id,
                        'brand_id' => $brands[$data['brand']]->id,
                        'unit_of_measure_id' => $unitsOfMeasure[$data['unit']]->id,
                        'status' => $data['status'] ?? ArticleStatus::Active,
                        'is_online_publishable' => $data['is_online_publishable'],
                    ],
                );
            }
        });
    }
}
