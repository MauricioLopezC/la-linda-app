<?php

namespace Database\Seeders\Inventory;

use App\Models\Catalog\Article;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseStock;
use Illuminate\Database\Seeder;

class WarehouseStockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $articles = Article::query()->get();
        $warehouses = Warehouse::query()->get();

        if ($articles->isEmpty() || $warehouses->isEmpty()) {
            return;
        }

        // Seed stock for articles in different warehouses with various stock levels
        $sampleStockConfig = [
            'ART-0001' => [
                'Depósito Central' => ['quantity' => 120, 'min_stock' => 20],
                'Depósito Norte' => ['quantity' => 45, 'min_stock' => 15],
                'Depósito E-commerce' => ['quantity' => 8, 'min_stock' => 10], // low_stock
            ],
            'ART-0002' => [
                'Depósito Central' => ['quantity' => 80, 'min_stock' => 15],
                'Depósito Norte' => ['quantity' => 5, 'min_stock' => 10], // low_stock
                'Depósito E-commerce' => ['quantity' => 0, 'min_stock' => 5], // out_of_stock
            ],
            'ART-0003' => [
                'Depósito Central' => ['quantity' => 200, 'min_stock' => 30],
                'Depósito Norte' => ['quantity' => 110, 'min_stock' => 25],
                'Depósito E-commerce' => ['quantity' => 50, 'min_stock' => 15],
            ],
            'ART-0004' => [
                'Depósito Central' => ['quantity' => 150, 'min_stock' => 25],
                'Depósito Norte' => ['quantity' => 0, 'min_stock' => 15], // out_of_stock
                'Depósito E-commerce' => ['quantity' => 30, 'min_stock' => 10],
            ],
            'ART-0005' => [
                'Depósito Central' => ['quantity' => 90, 'min_stock' => 20],
                'Depósito Norte' => ['quantity' => 12, 'min_stock' => 15], // low_stock
                'Depósito E-commerce' => ['quantity' => 40, 'min_stock' => 10],
            ],
            'ART-0006' => [
                'Depósito Central' => ['quantity' => 60, 'min_stock' => 15],
                'Depósito Norte' => ['quantity' => 25, 'min_stock' => 10],
                'Depósito E-commerce' => ['quantity' => 0, 'min_stock' => 10], // out_of_stock
            ],
            'ART-0007' => [
                'Depósito Central' => ['quantity' => 180, 'min_stock' => 30],
                'Depósito Norte' => ['quantity' => 95, 'min_stock' => 20],
                'Depósito E-commerce' => ['quantity' => 70, 'min_stock' => 15],
            ],
            'ART-0008' => [
                'Depósito Central' => ['quantity' => 250, 'min_stock' => 50],
                'Depósito Norte' => ['quantity' => 140, 'min_stock' => 30],
                'Depósito E-commerce' => ['quantity' => 85, 'min_stock' => 20],
            ],
        ];

        $warehousesByName = $warehouses->keyBy('name');
        $articlesByCode = $articles->keyBy('internal_code');

        foreach ($sampleStockConfig as $code => $warehouseConfigs) {
            $article = $articlesByCode->get($code);
            if (! $article) {
                continue;
            }

            foreach ($warehouseConfigs as $whName => $stockData) {
                $warehouse = $warehousesByName->get($whName);
                if (! $warehouse) {
                    continue;
                }

                WarehouseStock::firstOrCreate(
                    [
                        'article_id' => $article->id,
                        'warehouse_id' => $warehouse->id,
                    ],
                    [
                        'quantity' => $stockData['quantity'],
                        'min_stock' => $stockData['min_stock'],
                    ]
                );
            }
        }
    }
}
