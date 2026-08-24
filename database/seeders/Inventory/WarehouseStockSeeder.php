<?php

namespace Database\Seeders\Inventory;

use App\Actions\Inventory\RegisterStockAdjustment;
use App\Models\Catalog\Article;
use App\Models\Inventory\StockAdjustmentReason;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\User;
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
        $user = User::query()->first();
        $reason = StockAdjustmentReason::query()->where('name', 'Carga inicial de inventario')->first();

        if ($articles->isEmpty() || $warehouses->isEmpty() || ! $user || ! $reason) {
            return;
        }

        // Sample initial stock levels for articles across warehouses
        $sampleStockConfig = [
            'ART-0001' => [
                'Depósito Central' => 120,
                'Depósito Norte' => 45,
                'Depósito E-commerce' => 8,
            ],
            'ART-0002' => [
                'Depósito Central' => 80,
                'Depósito Norte' => 5,
            ],
            'ART-0003' => [
                'Depósito Central' => 200,
                'Depósito Norte' => 110,
                'Depósito E-commerce' => 50,
            ],
            'ART-0004' => [
                'Depósito Central' => 150,
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

        // Group initial items by warehouse
        $warehouseItems = [];
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

                $warehouseItems[$warehouse->id][] = [
                    'article_id' => $article->id,
                    'counted_quantity' => $quantity,
                ];
            }
        }

        $action = app(RegisterStockAdjustment::class);

        foreach ($warehouseItems as $warehouseId => $items) {
            $alreadySeeded = StockMovement::query()
                ->where('warehouse_id', $warehouseId)
                ->where('stock_adjustment_reason_id', $reason->id)
                ->exists();

            if ($alreadySeeded) {
                continue;
            }

            $action->execute([
                'warehouse_id' => $warehouseId,
                'stock_adjustment_reason_id' => $reason->id,
                'notes' => 'Carga inicial de inventario para apertura del sistema',
                'user_id' => $user->id,
                'items' => $items,
            ]);
        }
    }
}
