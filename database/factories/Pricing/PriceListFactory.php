<?php

namespace Database\Factories\Pricing;

use App\Enums\Pricing\PriceListChannel;
use App\Models\Pricing\PriceList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceList>
 */
class PriceListFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'channel' => fake()->randomElement(PriceListChannel::cases()),
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_to' => null,
            'is_active' => true,
            'description' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function futura(): static
    {
        return $this->state(fn (): array => [
            'valid_from' => now()->addMonth()->toDateString(),
            'valid_to' => null,
        ]);
    }

    public function vencida(): static
    {
        return $this->state(fn (): array => [
            'valid_from' => now()->subMonths(2)->toDateString(),
            'valid_to' => now()->subMonth()->toDateString(),
        ]);
    }
}
