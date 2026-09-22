<?php

namespace Database\Factories\Pricing;

use App\Models\Catalog\Article;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceListItem>
 */
class PriceListItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'price_list_id' => PriceList::factory(),
            'article_id' => Article::factory(),
            'price' => fake()->randomFloat(2, 1, 10000),
        ];
    }
}
