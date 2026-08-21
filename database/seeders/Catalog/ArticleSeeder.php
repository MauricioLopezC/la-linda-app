<?php

namespace Database\Seeders\Catalog;

use App\Enums\Catalog\ArticleStatus;
use App\Models\Catalog\Article;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\UnitOfMeasure;
use App\Models\Pricing\VatRate;
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
        $vatRates = VatRate::query()->get()->keyBy('description');

        $articles = [
            ['description' => 'Duraznos en almíbar 820g', 'internal_code' => 'ART-0001', 'category' => 'Conservas', 'brand' => 'Arcor', 'unit' => 'Unidad', 'vat' => 'General'],
            ['description' => 'Choclo en grano 300g', 'internal_code' => 'ART-0002', 'category' => 'Conservas', 'brand' => 'Arcor', 'unit' => 'Unidad', 'vat' => 'General'],
            ['description' => 'Harina 000 x 1kg', 'internal_code' => 'ART-0003', 'category' => 'Almacén', 'brand' => null, 'unit' => 'Kilogramo', 'vat' => 'General'],
            ['description' => 'Arroz largo fino x 1kg', 'internal_code' => 'ART-0004', 'category' => 'Almacén', 'brand' => null, 'unit' => 'Kilogramo', 'vat' => 'Reducida'],
            ['description' => 'Gaseosa cola 1.5L', 'internal_code' => 'ART-0005', 'category' => 'Gaseosas', 'brand' => null, 'unit' => 'Unidad', 'vat' => 'General', 'barcode' => '7790895000017'],
            ['description' => 'Agua saborizada 1.5L', 'internal_code' => 'ART-0006', 'category' => 'Gaseosas', 'brand' => null, 'unit' => 'Unidad', 'vat' => 'General'],
            ['description' => 'Agua mineral sin gas 2L', 'internal_code' => 'ART-0007', 'category' => 'Bebidas', 'brand' => null, 'unit' => 'Unidad', 'vat' => 'General', 'is_online_publishable' => true],
            ['description' => 'Leche entera x 1L', 'internal_code' => 'ART-0008', 'category' => 'Almacén', 'brand' => 'La Serenísima', 'unit' => 'Litro', 'vat' => 'Reducida'],
            ['description' => 'Yerba mate (presentación descontinuada)', 'internal_code' => 'ART-0009', 'category' => 'Almacén', 'brand' => null, 'unit' => 'Kilogramo', 'vat' => 'General', 'status' => ArticleStatus::Discontinued],
        ];

        Article::unguarded(function () use ($articles, $categories, $brands, $unitsOfMeasure, $vatRates): void {
            foreach ($articles as $data) {
                Article::firstOrCreate(
                    ['internal_code_normalized' => Article::normalizeUniqueValue($data['internal_code'])],
                    [
                        'description' => $data['description'],
                        'internal_code' => $data['internal_code'],
                        'barcode' => $data['barcode'] ?? null,
                        'category_id' => $categories[$data['category']]->id,
                        'brand_id' => $data['brand'] !== null ? $brands[$data['brand']]->id : null,
                        'unit_of_measure_id' => $unitsOfMeasure[$data['unit']]->id,
                        'vat_rate_id' => $vatRates[$data['vat']]->id,
                        'status' => $data['status'] ?? ArticleStatus::Active,
                        'is_online_publishable' => $data['is_online_publishable'] ?? false,
                    ],
                );
            }
        });
    }
}
