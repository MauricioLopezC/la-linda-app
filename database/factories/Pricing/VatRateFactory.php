<?php

namespace Database\Factories\Pricing;

use App\Models\Pricing\VatRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VatRate>
 */
class VatRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'description' => fake()->unique()->words(2, true),
            'percentage' => fake()->randomFloat(2, 0, 100),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
