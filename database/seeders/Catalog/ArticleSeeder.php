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
     * Fresh fruit and vegetables carry the reduced 10.5% VAT rate; everything else the general 21%.
     *
     * @var list<string>
     */
    private const REDUCED_VAT_CATEGORIES = ['Frutas', 'Verduras'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::query()->get()->keyBy('name');
        $brands = Brand::query()->get()->keyBy('name');
        $unitsOfMeasure = UnitOfMeasure::query()->get()->keyBy('name');
        $generalVatRate = VatRate::query()->where('percentage', 21)->firstOrFail();
        $reducedVatRate = VatRate::query()->where('percentage', 10.5)->firstOrFail();

        $articles = [
            [
                'description' => 'Duraznos en Almíbar en Mitades Arcor Lata 820 g',
                'internal_code' => 'ART-0001',
                'category' => 'Conservas',
                'brand' => 'Arcor',
                'unit' => 'Unidad',
                'barcode' => '7790580123456',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0001.webp',
            ],
            [
                'description' => 'Choclo Amarillo en Grano Entero Arcor Lata 300 g',
                'internal_code' => 'ART-0002',
                'category' => 'Conservas',
                'brand' => 'Arcor',
                'unit' => 'Unidad',
                'barcode' => '7790580654321',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0002.webp',
            ],
            [
                'description' => 'Harina de Trigo 000 Ultrarefinada Pureza Paquete 1 kg',
                'internal_code' => 'ART-0003',
                'category' => 'Harinas y Legumbres',
                'brand' => 'Pureza',
                'unit' => 'Unidad',
                'barcode' => '7791234567890',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0003.webp',
            ],
            [
                'description' => 'Arroz Largo Fino Tipo 00000 Gallo Bolsa 1 kg',
                'internal_code' => 'ART-0004',
                'category' => 'Arroz y Pastas',
                'brand' => 'Gallo',
                'unit' => 'Unidad',
                'barcode' => '7792345678901',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0004.webp',
            ],
            [
                'description' => 'Gaseosa Sabor Cola Clásica Coca-Cola Botella 1.5 L',
                'internal_code' => 'ART-0005',
                'category' => 'Gaseosas',
                'brand' => 'Coca-Cola',
                'unit' => 'Unidad',
                'barcode' => '7790895000017',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0005.webp',
            ],
            [
                'description' => 'Agua Saborizada Pomelo Sin Gas Levité Botella 1.5 L',
                'internal_code' => 'ART-0006',
                'category' => 'Aguas y Saborizadas',
                'brand' => 'Levité',
                'unit' => 'Unidad',
                'barcode' => '7793456889900',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0006.webp',
            ],
            [
                'description' => 'Agua Mineral Natural de Manantial Sin Gas Villavicencio Botella 2 L',
                'internal_code' => 'ART-0007',
                'category' => 'Aguas y Saborizadas',
                'brand' => 'Villavicencio',
                'unit' => 'Unidad',
                'barcode' => '7794567890123',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0007.webp',
            ],
            [
                'description' => 'Leche Entera Homogeneizada 3% Grasa La Serenísima Tetra Brik 1 L',
                'internal_code' => 'ART-0008',
                'category' => 'Leches',
                'brand' => 'La Serenísima',
                'unit' => 'Unidad',
                'barcode' => '7790742123456',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0008.webp',
            ],
            [
                'description' => 'Yerba Mate Tradicional Elaborada con Palo Playadito Paquete 1 kg',
                'internal_code' => 'ART-0009',
                'category' => 'Yerba Mate',
                'brand' => 'Playadito',
                'unit' => 'Unidad',
                'barcode' => '7795678901234',
                'status' => ArticleStatus::Inactive,
                'is_online_publishable' => false,
                'image_url' => '/images/articles/ART-0009.webp',
            ],
            [
                'description' => 'Aceite de Girasol Puro 100% Natura Botella 900 ml',
                'internal_code' => 'ART-0010',
                'category' => 'Aceites y Aderezos',
                'brand' => 'Natura',
                'unit' => 'Unidad',
                'barcode' => '7796789012345',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0010.webp',
            ],
            [
                'description' => 'Fideos Secos Spaghetti Guisero Lucchetti Paquete 500 g',
                'internal_code' => 'ART-0011',
                'category' => 'Arroz y Pastas',
                'brand' => 'Lucchetti',
                'unit' => 'Unidad',
                'barcode' => '7797890123456',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0011.webp',
            ],
            [
                'description' => 'Cerveza Rubia Clásica Lager Quilmes Botella 1 L',
                'internal_code' => 'ART-0012',
                'category' => 'Cervezas',
                'brand' => 'Quilmes',
                'unit' => 'Unidad',
                'barcode' => '7798901234567',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0012.webp',
            ],
            [
                'description' => 'Tomate Pelado Entero en Jugo Marolio Lata 400 g',
                'internal_code' => 'ART-0013',
                'category' => 'Conservas',
                'brand' => 'Marolio',
                'unit' => 'Unidad',
                'barcode' => '7799012345678',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0013.webp',
            ],
            [
                'description' => 'Azúcar Común Tipo A Ledesma Paquete 1 kg',
                'internal_code' => 'ART-0014',
                'category' => 'Azúcares y Endulzantes',
                'brand' => 'Ledesma',
                'unit' => 'Unidad',
                'barcode' => '7790123456789',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0014.webp',
            ],
            [
                'description' => 'Galletitas Dulces Sabor Vainilla Terrabusi Paquete 300 g',
                'internal_code' => 'ART-0015',
                'category' => 'Galletitas y Snacks',
                'brand' => 'Terrabusi',
                'unit' => 'Unidad',
                'barcode' => '7791234509876',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0015.webp',
            ],
            [
                'description' => 'Manzana Roja Granel',
                'internal_code' => 'ART-0016',
                'category' => 'Frutas',
                'unit' => 'Kilogramo',
                'barcode' => null,
                'is_online_publishable' => false,
                'image_url' => '/images/articles/ART-0016.webp',
            ],
            [
                'description' => 'Papa Blanca Granel',
                'internal_code' => 'ART-0017',
                'category' => 'Verduras',
                'unit' => 'Kilogramo',
                'barcode' => null,
                'is_online_publishable' => false,
                'image_url' => '/images/articles/ART-0017.webp',
            ],
            [
                'description' => 'Mermelada de Ciruela Clásica Arcor Frasco 454 g',
                'internal_code' => 'ART-0018',
                'category' => 'Conservas',
                'brand' => 'Arcor',
                'unit' => 'Unidad',
                'barcode' => '7790580987123',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0018.webp',
            ],
            [
                'description' => 'Arvejas Secas Remojadas Marolio Lata 300 g',
                'internal_code' => 'ART-0019',
                'category' => 'Conservas',
                'brand' => 'Marolio',
                'unit' => 'Unidad',
                'barcode' => '7799012876543',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0019.webp',
            ],
            [
                'description' => 'Fideos Guiseros Tirabuzón Lucchetti Paquete 500 g',
                'internal_code' => 'ART-0020',
                'category' => 'Arroz y Pastas',
                'brand' => 'Lucchetti',
                'unit' => 'Unidad',
                'barcode' => '7797890654321',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0020.webp',
            ],
            [
                'description' => 'Arroz Doble Carolina Especial Gallo Bolsa 1 kg',
                'internal_code' => 'ART-0021',
                'category' => 'Arroz y Pastas',
                'brand' => 'Gallo',
                'unit' => 'Unidad',
                'barcode' => '7792345112233',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0021.webp',
            ],
            [
                'description' => 'Mayonesa Clásica Reducida en Calorías Natura Doypack 500 g',
                'internal_code' => 'ART-0022',
                'category' => 'Aceites y Aderezos',
                'brand' => 'Natura',
                'unit' => 'Unidad',
                'barcode' => '7796789445566',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0022.webp',
            ],
            [
                'description' => 'Harina Leudante con Polvo de Hornear Pureza Paquete 1 kg',
                'internal_code' => 'ART-0023',
                'category' => 'Harinas y Legumbres',
                'brand' => 'Pureza',
                'unit' => 'Unidad',
                'barcode' => '7791234778899',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0023.webp',
            ],
            [
                'description' => 'Gaseosa Sabor Lima-Limón Sin Azúcar Coca-Cola Botella 1.5 L',
                'internal_code' => 'ART-0024',
                'category' => 'Gaseosas',
                'brand' => 'Coca-Cola',
                'unit' => 'Unidad',
                'barcode' => '7790895334455',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0024.webp',
            ],
            [
                'description' => 'Agua Saborizada Manzana Deliciosa Levité Botella 1.5 L',
                'internal_code' => 'ART-0025',
                'category' => 'Aguas y Saborizadas',
                'brand' => 'Levité',
                'unit' => 'Unidad',
                'barcode' => '7793456889901',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0025.webp',
            ],
            [
                'description' => 'Cerveza Negra Imperial Stout Quilmes Botella 1 L',
                'internal_code' => 'ART-0026',
                'category' => 'Cervezas',
                'brand' => 'Quilmes',
                'unit' => 'Unidad',
                'barcode' => '7798901556677',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0026.webp',
            ],
            [
                'description' => 'Yogur Entero Batido con Frutillas La Serenísima Pote 120 g',
                'internal_code' => 'ART-0027',
                'category' => 'Yogures',
                'brand' => 'La Serenísima',
                'unit' => 'Unidad',
                'barcode' => '7790742556677',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0027.webp',
            ],
            [
                'description' => 'Queso Crema Clásico Finlandia La Serenísima Pote 300 g',
                'internal_code' => 'ART-0028',
                'category' => 'Quesos',
                'brand' => 'La Serenísima',
                'unit' => 'Unidad',
                'barcode' => '7790742990011',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0028.webp',
            ],
            [
                'description' => 'Leche Descremada 0% Grasa Reducida en Lactosa La Serenísima Tetra Brik 1 L',
                'internal_code' => 'ART-0029',
                'category' => 'Leches',
                'brand' => 'La Serenísima',
                'unit' => 'Unidad',
                'barcode' => '7790742332211',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0029.webp',
            ],
            [
                'description' => 'Yerba Mate con Hierbas Serranas Playadito Paquete 500 g',
                'internal_code' => 'ART-0030',
                'category' => 'Yerba Mate',
                'brand' => 'Playadito',
                'unit' => 'Unidad',
                'barcode' => '7795678443322',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0030.webp',
            ],
            [
                'description' => 'Galletitas Surtidas Variedad Terrabusi Paquete 400 g',
                'internal_code' => 'ART-0031',
                'category' => 'Galletitas y Snacks',
                'brand' => 'Terrabusi',
                'unit' => 'Unidad',
                'barcode' => '7791234998877',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0031.webp',
            ],
            [
                'description' => 'Jabón Líquido para Ropa Concentrado Botella 800 ml',
                'internal_code' => 'ART-0032',
                'category' => 'Cuidado de la Ropa',
                'unit' => 'Unidad',
                'barcode' => '7798888111222',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0032.webp',
            ],
            [
                'description' => 'Lavavajillas Ultra Concentrado Aroma Limón Botella 500 ml',
                'internal_code' => 'ART-0033',
                'category' => 'Desinfectantes y Lavavajillas',
                'unit' => 'Unidad',
                'barcode' => '7798888333444',
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0033.webp',
            ],
            [
                'description' => 'Naranja de Ombligo Fresca Seleccionada',
                'internal_code' => 'ART-0034',
                'category' => 'Frutas',
                'unit' => 'Kilogramo',
                'barcode' => null,
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0034.webp',
            ],
            [
                'description' => 'Tomate Redondo Premium',
                'internal_code' => 'ART-0035',
                'category' => 'Verduras',
                'unit' => 'Kilogramo',
                'barcode' => null,
                'is_online_publishable' => true,
                'image_url' => '/images/articles/ART-0035.webp',
            ],
        ];

        Article::unguarded(function () use ($articles, $categories, $brands, $unitsOfMeasure, $generalVatRate, $reducedVatRate): void {
            foreach ($articles as $data) {
                Article::updateOrCreate(
                    ['internal_code_normalized' => Article::normalizeUniqueValue($data['internal_code'])],
                    [
                        'description' => $data['description'],
                        'internal_code' => $data['internal_code'],
                        'barcode' => $data['barcode'],
                        'category_id' => $categories[$data['category']]->id,
                        'brand_id' => isset($data['brand']) ? $brands[$data['brand']]->id : null,
                        'unit_of_measure_id' => $unitsOfMeasure[$data['unit']]->id,
                        'vat_rate_id' => in_array($data['category'], self::REDUCED_VAT_CATEGORIES, true)
                            ? $reducedVatRate->id
                            : $generalVatRate->id,
                        'status' => $data['status'] ?? ArticleStatus::Active,
                        'is_online_publishable' => $data['is_online_publishable'],
                        'image_url' => $data['image_url'],
                    ],
                );
            }
        });
    }
}
