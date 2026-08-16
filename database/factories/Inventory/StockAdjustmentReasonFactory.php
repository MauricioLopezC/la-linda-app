<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\StockAdjustmentReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustmentReason>
 */
class StockAdjustmentReasonFactory extends Factory
{
    protected $model = StockAdjustmentReason::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word().' '.fake()->word());

        return [
            'name' => $name,
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the adjustment reason is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
