<?php

namespace Database\Factories\Sales;

use App\Enums\Sales\CashSessionStatus;
use App\Models\Sales\CashSession;
use App\Models\Sales\PointOfSale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSession>
 */
class CashSessionFactory extends Factory
{
    protected $model = CashSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'point_of_sale_id' => PointOfSale::factory(),
            'user_id' => User::factory(),
            'status' => CashSessionStatus::Open,
            'opened_at' => now(),
            'opening_amount' => '0.00',
            'closed_at' => null,
            'closing_notes' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => CashSessionStatus::Closed,
            'closed_at' => now(),
        ]);
    }
}
