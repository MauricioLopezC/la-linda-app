<?php

namespace Database\Factories\Sales;

use App\Enums\Sales\PaymentMethodKind;
use App\Models\Sales\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'kind' => PaymentMethodKind::Other,
            'is_enabled_online' => false,
            'is_active' => true,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn (): array => [
            'kind' => PaymentMethodKind::Cash,
        ]);
    }

    public function card(): static
    {
        return $this->state(fn (): array => [
            'kind' => PaymentMethodKind::Card,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function enabledOnline(): static
    {
        return $this->state(fn (): array => [
            'is_enabled_online' => true,
        ]);
    }
}
