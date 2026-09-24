<?php

namespace Database\Factories\Sales;

use App\Models\Catalog\Article;
use App\Models\Pricing\PriceList;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

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
            'sale_id' => Sale::factory(),
            'article_id' => Article::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'price_list_id' => PriceList::factory()->particular(),
            'line_total' => SaleItem::calculateLineTotal($quantity, $unitPrice),
        ];
    }
}
