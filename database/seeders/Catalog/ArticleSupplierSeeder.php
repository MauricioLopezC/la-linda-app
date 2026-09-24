<?php

namespace Database\Seeders\Catalog;

use App\Models\Catalog\Article;
use App\Models\Catalog\ArticleSupplier;
use App\Models\Purchasing\Supplier;
use Illuminate\Database\Seeder;

class ArticleSupplierSeeder extends Seeder
{
    /**
     * Seed which articles each demo supplier provides and the code the supplier uses for them.
     *
     * `last_cost` is left empty on purpose: it's derived from the supplier's invoices
     * (`UpdateLastPurchaseCost`), so SupplierVoucherSeeder fills it where applicable.
     */
    public function run(): void
    {
        $associationsBySupplierTaxId = [
            // Molinos Río de la Plata S.A.
            '30500858628' => [
                ['internal_code' => 'ART-0003', 'supplier_article_code' => 'MRP-10450', 'notes' => 'Bulto x 10 unidades.'],
                ['internal_code' => 'ART-0004', 'supplier_article_code' => 'MRP-20118', 'notes' => 'Bulto x 10 unidades.'],
                ['internal_code' => 'ART-0011', 'supplier_article_code' => 'MRP-30731', 'notes' => 'Bulto x 20 unidades.'],
                ['internal_code' => 'ART-0010', 'supplier_article_code' => 'MRP-40922'],
            ],
            // Arcor S.A.I.C.
            '30502793175' => [
                ['internal_code' => 'ART-0001', 'supplier_article_code' => 'ARC-7701', 'notes' => 'Caja x 12 latas.'],
                ['internal_code' => 'ART-0002', 'supplier_article_code' => 'ARC-7715', 'notes' => 'Caja x 24 latas.'],
                ['internal_code' => 'ART-0015', 'supplier_article_code' => 'ARC-8830'],
            ],
            // Mastellone Hermanos S.A.
            '30500511849' => [
                ['internal_code' => 'ART-0008', 'supplier_article_code' => 'LS-000125', 'notes' => 'Cajón x 12 unidades. Cadena de frío.'],
            ],
            // Cervecería y Maltería Quilmes S.A.I.C.A. y G.
            '30500949461' => [
                ['internal_code' => 'ART-0012', 'supplier_article_code' => 'CMQ-1001', 'notes' => 'Cajón x 12 botellas retornables.'],
                ['internal_code' => 'ART-0005', 'supplier_article_code' => 'CMQ-2050'],
                ['internal_code' => 'ART-0006', 'supplier_article_code' => 'CMQ-2074'],
                ['internal_code' => 'ART-0007', 'supplier_article_code' => 'CMQ-3012'],
            ],
            // Distribuidora Mayorista San Cayetano
            '20289456121' => [
                ['internal_code' => 'ART-0013', 'supplier_article_code' => 'SC-0133'],
                ['internal_code' => 'ART-0014', 'supplier_article_code' => 'SC-0147', 'notes' => 'Fardo x 10 paquetes.'],
                ['internal_code' => 'ART-0004', 'supplier_article_code' => 'SC-0042'],
                ['internal_code' => 'ART-0010', 'supplier_article_code' => 'SC-0105'],
                ['internal_code' => 'ART-0016', 'supplier_article_code' => 'SC-FV-01', 'notes' => 'Se factura por kg pesado en balanza.'],
                ['internal_code' => 'ART-0017', 'supplier_article_code' => 'SC-FV-02', 'notes' => 'Se factura por kg pesado en balanza.'],
            ],
        ];

        $suppliers = Supplier::query()
            ->whereIn('tax_id', array_keys($associationsBySupplierTaxId))
            ->get()
            ->keyBy('tax_id');

        $articles = Article::query()
            ->active()
            ->get()
            ->keyBy('internal_code');

        foreach ($associationsBySupplierTaxId as $taxId => $associations) {
            $supplier = $suppliers->get($taxId);

            if ($supplier === null) {
                continue;
            }

            foreach ($associations as $association) {
                $article = $articles->get($association['internal_code']);

                if ($article === null) {
                    continue;
                }

                ArticleSupplier::query()->firstOrCreate(
                    ['article_id' => $article->id, 'supplier_id' => $supplier->id],
                    [
                        'supplier_article_code' => $association['supplier_article_code'],
                        'notes' => $association['notes'] ?? null,
                    ],
                );
            }
        }
    }
}
