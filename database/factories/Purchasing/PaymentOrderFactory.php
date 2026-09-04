<?php

namespace Database\Factories\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Purchasing\PaymentOrderStatus;
use App\Models\Purchasing\PaymentOrder;
use App\Models\Purchasing\Supplier;
use App\Models\Sales\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentOrder>
 */
class PaymentOrderFactory extends Factory
{
    use ConvertsMoneyToCents;

    protected $model = PaymentOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'order_number' => fake()->unique()->numerify('OP-########'),
            'date' => fake()->dateTimeBetween('-30 days', 'now'),
            'total_amount' => $this->centsToMoney(fake()->numberBetween(10_000, 5_000_000)),
            'status' => PaymentOrderStatus::Issued,
            'notes' => fake()->optional()->sentence(),
            'user_id' => User::factory(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => PaymentOrderStatus::Cancelled]);
    }
}
