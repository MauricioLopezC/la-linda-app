<?php

namespace Database\Seeders\Inventory;

use App\Models\Catalog\Article;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\Warehouse;
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

        // Seed stock balances for articles in different warehouses with various stock levels
        $sampleStockConfig = [
            'ART-0001' => [
                'Depósito Central' => 120,
                'Depósito Norte' => 45,
                'Depósito E-commerce' => 8,
            ],
            'ART-0002' => [
                'Depósito Central' => 80,
                'Depósito Norte' => 5,
                'Depósito E-commerce' => 0,
            ],
            'ART-0003' => [
                'Depósito Central' => 200,
                'Depósito Norte' => 110,
                'Depósito E-commerce' => 50,
            ],
            'ART-0004' => [
                'Depósito Central' => 150,
                'Depósito Norte' => 0,
                'Depósito E-commerce' => 30,
            ],
            'ART-0005' => [
                'Depósito Central' => 90,
                'Depósito Norte' => 12,
                'Depósito E-commerce' => 40,
            ],
            'ART-0006' => [
                'Depósito Central' => 60,
                'Depósito Norte' => 25,
                'Depósito E-commerce' => 0,
            ],
            'ART-0007' => [
                'Depósito Central' => 180,
                'Depósito Norte' => 95,
                'Depósito E-commerce' => 70,
            ],
            'ART-0008' => [
                'Depósito Central' => 250,
                'Depósito Norte' => 140,
                'Depósito E-commerce' => 85,
            ],
        ];

        $warehousesByName = $warehouses->keyBy('name');
        $articlesByCode = $articles->keyBy('internal_code');

        foreach ($sampleStockConfig as $code => $warehouseConfigs) {
            $article = $articlesByCode->get($code);
            if (! $article) {
                continue;
            }

            foreach ($warehouseConfigs as $whName => $quantity) {
                $warehouse = $warehousesByName->get($whName);
                if (! $warehouse) {
                    continue;
                }

                StockBalance::firstOrCreate(
                    [
                        'article_id' => $article->id,
                        'warehouse_id' => $warehouse->id,
                    ],
                    ['quantity' => $quantity]
                );
            }
        }
    }
}
