<?php

namespace Database\Factories\Inventory;

use App\Models\Catalog\Article;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockBalance>
 */
class StockBalanceFactory extends Factory
{
    protected $model = StockBalance::class;

    /**
     * Define the model's default state.
     *
     * Writing a balance directly is a test-only shortcut. In the application the quantity only
     * ever changes through the movement action of HU-017.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'warehouse_id' => Warehouse::factory(),
            'quantity' => fake()->numberBetween(1, 500),
        ];
    }

    /**
     * Indicate that the article has no stock left in the warehouse.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes): array => [
            'quantity' => 0,
        ]);
    }
}
