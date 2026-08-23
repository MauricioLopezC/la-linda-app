<?php

namespace Database\Factories\Inventory;

use App\Models\Catalog\Article;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovementItem>
 */
class StockMovementItemFactory extends Factory
{
    protected $model = StockMovementItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_movement_id' => StockMovement::factory(),
            'article_id' => Article::factory(),
            'quantity' => fake()->numberBetween(1, 100),
            'system_quantity' => null,
        ];
    }

    /**
     * Indicate that the line takes stock out of the warehouse.
     */
    public function outgoing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'quantity' => -abs((float) $attributes['quantity']),
        ]);
    }

    /**
     * Indicate that the line comes from a manual count, recording the previous balance.
     */
    public function counted(float $systemQuantity): static
    {
        return $this->state(fn (array $attributes): array => [
            'system_quantity' => $systemQuantity,
        ]);
    }
}
