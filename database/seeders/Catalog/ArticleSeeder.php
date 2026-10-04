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
                'image_url' => 'https://images.unsplash.com/photo-1595231776515-ddffb1f4eb73?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Choclo Amarillo en Grano Entero Arcor Lata 300 g',
                'internal_code' => 'ART-0002',
                'category' => 'Conservas',
                'brand' => 'Arcor',
                'unit' => 'Unidad',
                'barcode' => '7790580654321',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1551754655-cd27e38d2076?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Harina de Trigo 000 Ultrarefinada Pureza Paquete 1 kg',
                'internal_code' => 'ART-0003',
                'category' => 'Harinas y Legumbres',
                'brand' => 'Pureza',
                'unit' => 'Unidad',
                'barcode' => '7791234567890',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Arroz Largo Fino Tipo 00000 Gallo Bolsa 1 kg',
                'internal_code' => 'ART-0004',
                'category' => 'Arroz y Pastas',
                'brand' => 'Gallo',
                'unit' => 'Unidad',
                'barcode' => '7792345678901',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1586201375761-83865001e31c?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Gaseosa Sabor Cola Clásica Coca-Cola Botella 1.5 L',
                'internal_code' => 'ART-0005',
                'category' => 'Gaseosas',
                'brand' => 'Coca-Cola',
                'unit' => 'Unidad',
                'barcode' => '7790895000017',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Agua Saborizada Pomelo Sin Gas Levité Botella 1.5 L',
                'internal_code' => 'ART-0006',
                'category' => 'Aguas y Saborizadas',
                'brand' => 'Levité',
                'unit' => 'Unidad',
                'barcode' => '7793456889900',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Agua Mineral Natural de Manantial Sin Gas Villavicencio Botella 2 L',
                'internal_code' => 'ART-0007',
                'category' => 'Aguas y Saborizadas',
                'brand' => 'Villavicencio',
                'unit' => 'Unidad',
                'barcode' => '7794567890123',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1560023907-5f339617ea30?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Leche Entera Homogeneizada 3% Grasa La Serenísima Tetra Brik 1 L',
                'internal_code' => 'ART-0008',
                'category' => 'Leches',
                'brand' => 'La Serenísima',
                'unit' => 'Unidad',
                'barcode' => '7790742123456',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1563636619-e9143da7973b?auto=format&fit=crop&w=600&q=80',
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
                'image_url' => 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Aceite de Girasol Puro 100% Natura Botella 900 ml',
                'internal_code' => 'ART-0010',
                'category' => 'Aceites y Aderezos',
                'brand' => 'Natura',
                'unit' => 'Unidad',
                'barcode' => '7796789012345',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Fideos Secos Spaghetti Guisero Lucchetti Paquete 500 g',
                'internal_code' => 'ART-0011',
                'category' => 'Arroz y Pastas',
                'brand' => 'Lucchetti',
                'unit' => 'Unidad',
                'barcode' => '7797890123456',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1621996346565-e3d5d6281084?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Cerveza Rubia Clásica Lager Quilmes Botella 1 L',
                'internal_code' => 'ART-0012',
                'category' => 'Cervezas',
                'brand' => 'Quilmes',
                'unit' => 'Unidad',
                'barcode' => '7798901234567',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1608270104193-4a0bfa93433a?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Tomate Pelado Entero en Jugo Marolio Lata 400 g',
                'internal_code' => 'ART-0013',
                'category' => 'Conservas',
                'brand' => 'Marolio',
                'unit' => 'Unidad',
                'barcode' => '7799012345678',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Azúcar Común Tipo A Ledesma Paquete 1 kg',
                'internal_code' => 'ART-0014',
                'category' => 'Azúcares y Endulzantes',
                'brand' => 'Ledesma',
                'unit' => 'Unidad',
                'barcode' => '7790123456789',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1587735243615-c03f25aaff15?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Galletitas Dulces Sabor Vainilla Terrabusi Paquete 300 g',
                'internal_code' => 'ART-0015',
                'category' => 'Galletitas y Snacks',
                'brand' => 'Terrabusi',
                'unit' => 'Unidad',
                'barcode' => '7791234509876',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Manzana Roja Granel',
                'internal_code' => 'ART-0016',
                'category' => 'Frutas',
                'unit' => 'Kilogramo',
                'barcode' => null,
                'is_online_publishable' => false,
                'image_url' => 'https://images.unsplash.com/photo-1560806887-1e4cd0b6cbd6?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Papa Blanca Granel',
                'internal_code' => 'ART-0017',
                'category' => 'Verduras',
                'unit' => 'Kilogramo',
                'barcode' => null,
                'is_online_publishable' => false,
                'image_url' => 'https://images.unsplash.com/photo-1518977676601-b53f82aba655?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Mermelada de Ciruela Clásica Arcor Frasco 454 g',
                'internal_code' => 'ART-0018',
                'category' => 'Conservas',
                'brand' => 'Arcor',
                'unit' => 'Unidad',
                'barcode' => '7790580987123',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1589182373726-e4f658ab50f0?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Arvejas Secas Remojadas Marolio Lata 300 g',
                'internal_code' => 'ART-0019',
                'category' => 'Conservas',
                'brand' => 'Marolio',
                'unit' => 'Unidad',
                'barcode' => '7799012876543',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1592394533824-9440e5d68530?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Fideos Guiseros Tirabuzón Lucchetti Paquete 500 g',
                'internal_code' => 'ART-0020',
                'category' => 'Arroz y Pastas',
                'brand' => 'Lucchetti',
                'unit' => 'Unidad',
                'barcode' => '7797890654321',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1551462147-ff29053fad31?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Arroz Doble Carolina Especial Gallo Bolsa 1 kg',
                'internal_code' => 'ART-0021',
                'category' => 'Arroz y Pastas',
                'brand' => 'Gallo',
                'unit' => 'Unidad',
                'barcode' => '7792345112233',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1536304993881-ff6e9eefa2a6?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Mayonesa Clásica Reducida en Calorías Natura Doypack 500 g',
                'internal_code' => 'ART-0022',
                'category' => 'Aceites y Aderezos',
                'brand' => 'Natura',
                'unit' => 'Unidad',
                'barcode' => '7796789445566',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1528750997573-59b89d56f4f7?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Harina Leudante con Polvo de Hornear Pureza Paquete 1 kg',
                'internal_code' => 'ART-0023',
                'category' => 'Harinas y Legumbres',
                'brand' => 'Pureza',
                'unit' => 'Unidad',
                'barcode' => '7791234778899',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1574085733277-851d9d856a3a?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Gaseosa Sabor Lima-Limón Sin Azúcar Coca-Cola Botella 1.5 L',
                'internal_code' => 'ART-0024',
                'category' => 'Gaseosas',
                'brand' => 'Coca-Cola',
                'unit' => 'Unidad',
                'barcode' => '7790895334455',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Agua Saborizada Manzana Deliciosa Levité Botella 1.5 L',
                'internal_code' => 'ART-0025',
                'category' => 'Aguas y Saborizadas',
                'brand' => 'Levité',
                'unit' => 'Unidad',
                'barcode' => '7793456889901',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1556881286-fc6915169721?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Cerveza Negra Imperial Stout Quilmes Botella 1 L',
                'internal_code' => 'ART-0026',
                'category' => 'Cervezas',
                'brand' => 'Quilmes',
                'unit' => 'Unidad',
                'barcode' => '7798901556677',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1535958636474-b021ee887b13?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Yogur Entero Batido con Frutillas La Serenísima Pote 120 g',
                'internal_code' => 'ART-0027',
                'category' => 'Yogures',
                'brand' => 'La Serenísima',
                'unit' => 'Unidad',
                'barcode' => '7790742556677',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Queso Crema Clásico Finlandia La Serenísima Pote 300 g',
                'internal_code' => 'ART-0028',
                'category' => 'Quesos',
                'brand' => 'La Serenísima',
                'unit' => 'Unidad',
                'barcode' => '7790742990011',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1624806992066-5ffcf7ca186b?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Leche Descremada 0% Grasa Reducida en Lactosa La Serenísima Tetra Brik 1 L',
                'internal_code' => 'ART-0029',
                'category' => 'Leches',
                'brand' => 'La Serenísima',
                'unit' => 'Unidad',
                'barcode' => '7790742332211',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1550583724-b2692b85b150?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Yerba Mate con Hierbas Serranas Playadito Paquete 500 g',
                'internal_code' => 'ART-0030',
                'category' => 'Yerba Mate',
                'brand' => 'Playadito',
                'unit' => 'Unidad',
                'barcode' => '7795678443322',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1597318181409-cf64d0b5d8a2?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Galletitas Surtidas Variedad Terrabusi Paquete 400 g',
                'internal_code' => 'ART-0031',
                'category' => 'Galletitas y Snacks',
                'brand' => 'Terrabusi',
                'unit' => 'Unidad',
                'barcode' => '7791234998877',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Jabón Líquido para Ropa Concentrado Botella 800 ml',
                'internal_code' => 'ART-0032',
                'category' => 'Cuidado de la Ropa',
                'unit' => 'Unidad',
                'barcode' => '7798888111222',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1585421514738-01798e348b17?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Lavavajillas Ultra Concentrado Aroma Limón Botella 500 ml',
                'internal_code' => 'ART-0033',
                'category' => 'Desinfectantes y Lavavajillas',
                'unit' => 'Unidad',
                'barcode' => '7798888333444',
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Naranja de Ombligo Fresca Seleccionada',
                'internal_code' => 'ART-0034',
                'category' => 'Frutas',
                'unit' => 'Kilogramo',
                'barcode' => null,
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1582979512210-99b6a53386f9?auto=format&fit=crop&w=600&q=80',
            ],
            [
                'description' => 'Tomate Redondo Premium',
                'internal_code' => 'ART-0035',
                'category' => 'Verduras',
                'unit' => 'Kilogramo',
                'barcode' => null,
                'is_online_publishable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?auto=format&fit=crop&w=600&q=80',
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
