<?php

namespace Database\Factories\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Purchasing\PurchaseOrderStatus;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    use ConvertsMoneyToCents;

    protected $model = PurchaseOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issueDate = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'supplier_id' => Supplier::factory(),
            'warehouse_id' => Warehouse::factory(),
            'order_number' => fake()->unique()->numerify('OC-######'),
            'payment_terms' => fake()->optional()->randomElement(['Contado 30 días', 'Pago contra entrega', 'Transferencia 15 días']),
            'issue_date' => $issueDate->format('Y-m-d'),
            'expected_delivery_date' => function (array $attributes) use ($issueDate): ?string {
                if (! fake()->boolean(70)) {
                    return null;
                }

                $baseDate = isset($attributes['issue_date'])
                    ? Carbon::parse((string) $attributes['issue_date'])
                    : Carbon::parse($issueDate);

                return $baseDate->copy()->addDays(fake()->numberBetween(1, 30))->toDateString();
            },
            'total_amount' => '0.00',
            'status' => PurchaseOrderStatus::Draft,
            'notes' => fake()->optional()->sentence(),
            'user_id' => User::factory(),
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_reason' => null,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (): array => ['status' => PurchaseOrderStatus::Issued]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseOrderStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => User::factory(),
            'cancellation_reason' => 'Cancelación de prueba',
        ]);
    }
}
