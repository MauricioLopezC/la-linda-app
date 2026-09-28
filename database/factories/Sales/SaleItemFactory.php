<?php

namespace Database\Factories\Sales;

use App\Models\Catalog\Article;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\VatRate;
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
     * Define the model's default state. The VAT rate is the article's, as AddArticleToSale
     * freezes it; net and VAT are derived by the model on save.
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
            'vat_rate_id' => fn (array $attributes): ?int => Article::find((int) $attributes['article_id'])?->vat_rate_id,
            'vat_rate' => fn (array $attributes): ?float => VatRate::find((int) $attributes['vat_rate_id'])?->percentage,
        ];
    }
}
