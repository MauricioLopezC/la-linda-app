<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\StockMovementType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StockMovementType>
 */
class StockMovementTypeFactory extends Factory
{
    protected $model = StockMovementType::class;

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
            'code' => Str::slug($name, '_'),
            'sign' => fake()->randomElement([1, -1]),
            'description' => fake()->optional()->sentence(),
            'is_system' => false,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the movement type is a system-protected type.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    /**
     * Indicate that the movement type is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the movement represents an addition (suma / ingreso).
     */
    public function addition(): static
    {
        return $this->state(fn (array $attributes) => [
            'sign' => 1,
        ]);
    }

    /**
     * Indicate that the movement represents a deduction (resta / egreso).
     */
    public function deduction(): static
    {
        return $this->state(fn (array $attributes) => [
            'sign' => -1,
        ]);
    }
}
