<?php

namespace Database\Factories\Inventory;

use App\Models\Catalog\Article;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseStock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarehouseStock>
 */
class WarehouseStockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'warehouse_id' => Warehouse::factory(),
            'quantity' => fake()->randomFloat(2, 10, 200),
            'min_stock' => fake()->randomFloat(2, 5, 20),
        ];
    }

    /**
     * Indicate that the stock is completely depleted.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 0,
            'min_stock' => 10,
        ]);
    }

    /**
     * Indicate that the stock is below the minimum threshold.
     */
    public function belowMinimum(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 3,
            'min_stock' => 10,
        ]);
    }

    /**
     * Indicate that the stock is well above the minimum threshold.
     */
    public function inStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 50,
            'min_stock' => 10,
        ]);
    }
}
