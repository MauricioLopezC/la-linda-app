<?php

namespace Database\Factories\Sales;

use App\Enums\Sales\CashMovementType;
use App\Models\Sales\CashMovement;
use App\Models\Sales\CashSession;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
{
    protected $model = CashMovement::class;

    /**
     * Define the model's default state: a cash income with its reason.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cash_session_id' => CashSession::factory(),
            'type' => CashMovementType::Income,
            'payment_method_id' => PaymentMethod::factory()->cash(),
            'amount' => number_format(fake()->randomFloat(2, 100, 20000), 2, '.', ''),
            'sale_id' => null,
            'tendered_amount' => null,
            'reason' => fake()->sentence(3),
            'user_id' => User::factory(),
        ];
    }

    public function expense(): static
    {
        return $this->state(fn (): array => [
            'type' => CashMovementType::Expense,
        ]);
    }

    public function opening(): static
    {
        return $this->state(fn (): array => [
            'type' => CashMovementType::Opening,
            'reason' => null,
        ]);
    }

    /**
     * A payment of the given sale, in its cash session.
     */
    public function forSale(Sale $sale): static
    {
        return $this->state(fn (): array => [
            'cash_session_id' => $sale->cash_session_id,
            'type' => CashMovementType::Sale,
            'sale_id' => $sale->id,
            'reason' => null,
        ]);
    }
}
