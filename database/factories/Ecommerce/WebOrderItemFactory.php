<?php

namespace Database\Factories\Ecommerce;

use App\Models\Catalog\Article;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Pricing\PriceList;
use App\Models\Sales\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebOrderItem>
 */
class WebOrderItemFactory extends Factory
{
    protected $model = WebOrderItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = '1.000';
        $unitPrice = number_format(fake()->randomFloat(2, 100, 5000), 2, '.', '');

        return [
            'web_order_id' => WebOrder::factory(),
            'article_id' => Article::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'price_list_id' => PriceList::factory()->particular(),
            'line_total' => SaleItem::calculateLineTotal($quantity, $unitPrice),
        ];
    }
}
