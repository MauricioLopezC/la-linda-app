<?php

namespace Database\Factories\Sales;

use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Customers\Customer;
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
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'point_of_sale_id' => PointOfSale::factory(),
            'channel' => SaleChannel::Mostrador,
            'customer_id' => Customer::factory(),
            'user_id' => User::factory(),
            'opened_at' => now(),
            'status' => SaleStatus::Open,
            'total_amount' => '0.00',
        ];
    }

    public function discarded(): static
    {
        return $this->state(fn (): array => [
            'status' => SaleStatus::Discarded,
        ]);
    }
}
