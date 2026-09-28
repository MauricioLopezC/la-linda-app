<?php

namespace Database\Factories\Sales;

use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Customers\Customer;
use App\Models\Sales\CashSession;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * Define the model's default state: a counter sale in the open cash session of its point
     * of sale, which is created when there is none.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'point_of_sale_id' => PointOfSale::factory(),
            'channel' => SaleChannel::Mostrador,
            'cash_session_id' => fn (array $attributes): int => CashSession::query()
                ->open()
                ->where('point_of_sale_id', $attributes['point_of_sale_id'])
                ->value('id')
                ?? CashSession::factory()->create(['point_of_sale_id' => $attributes['point_of_sale_id']])->id,
            'customer_id' => Customer::factory(),
            'user_id' => User::factory(),
            'opened_at' => now(),
            'status' => SaleStatus::Open,
            'total_amount' => '0.00',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => SaleStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }

    public function online(): static
    {
        return $this->state(fn (): array => [
            'channel' => SaleChannel::Online,
            'cash_session_id' => null,
        ]);
    }

    public function discarded(): static
    {
        return $this->state(fn (): array => [
            'status' => SaleStatus::Discarded,
        ]);
    }
}
