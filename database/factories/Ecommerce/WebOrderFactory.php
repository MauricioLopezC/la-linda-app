<?php

namespace Database\Factories\Ecommerce;

use App\Enums\Ecommerce\DeliveryMethod;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\WebOrder;
use App\Models\Organization\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebOrder>
 */
class WebOrderFactory extends Factory
{
    protected $model = WebOrder::class;

    /**
     * Define the model's default state: a pending order picked up at a branch.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $itemsAmount = number_format(fake()->randomFloat(2, 1000, 50000), 2, '.', '');

        return [
            'number' => fn (): int => (int) WebOrder::query()->max('number') + 1,
            'customer_id' => Customer::factory(),
            'status' => WebOrderStatus::Pending,
            'delivery_method' => DeliveryMethod::Pickup,
            'pickup_branch_id' => Branch::factory(),
            'shipping_address' => null,
            'shipping_notes' => null,
            'items_amount' => $itemsAmount,
            'shipping_cost' => '0.00',
            'total_amount' => $itemsAmount,
            'placed_at' => now(),
        ];
    }

    public function shipping(string $shippingCost = '2500.00'): static
    {
        return $this->state(fn (array $attributes): array => [
            'delivery_method' => DeliveryMethod::Shipping,
            'pickup_branch_id' => null,
            'shipping_address' => fake()->streetAddress(),
            'shipping_cost' => $shippingCost,
            'total_amount' => number_format((float) $attributes['items_amount'] + (float) $shippingCost, 2, '.', ''),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WebOrderStatus::Paid,
            'mp_payment_id' => (string) fake()->unique()->numerify('##########'),
            'paid_amount' => $attributes['total_amount'] ?? $attributes['items_amount'],
            'paid_at' => now(),
        ]);
    }
}
