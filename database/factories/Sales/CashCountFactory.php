<?php

namespace Database\Factories\Sales;

use App\Enums\Sales\CashCountMoment;
use App\Enums\Sales\CashDenomination;
use App\Models\Sales\CashCount;
use App\Models\Sales\CashSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashCount>
 */
class CashCountFactory extends Factory
{
    protected $model = CashCount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cash_session_id' => CashSession::factory(),
            'moment' => CashCountMoment::Opening,
            'denomination' => (string) fake()->randomElement(CashDenomination::cases())->value,
            'quantity' => fake()->numberBetween(0, 20),
        ];
    }

    public function closing(): static
    {
        return $this->state(fn (): array => [
            'moment' => CashCountMoment::Closing,
        ]);
    }
}
