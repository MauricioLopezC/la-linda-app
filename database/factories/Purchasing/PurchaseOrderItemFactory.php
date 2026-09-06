<?php

namespace Database\Factories\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Models\Catalog\Article;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    use ConvertsMoneyToCents;

    protected $model = PurchaseOrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 100);
        $unitPrice = fake()->randomFloat(2, 50, 5000);
        $lineTotal = round($quantity * $unitPrice, 2);

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'article_id' => Article::factory(),
            'quantity' => number_format($quantity, 3, '.', ''),
            'unit_price' => number_format($unitPrice, 2, '.', ''),
            'line_total' => number_format($lineTotal, 2, '.', ''),
        ];
    }
}
