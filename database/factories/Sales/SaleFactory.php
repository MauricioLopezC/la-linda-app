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
            'cash_session_id' => function (array $attributes): int {
                $posId = $attributes['point_of_sale_id'];
                $existing = CashSession::query()
                    ->open()
                    ->where('point_of_sale_id', $posId)
                    ->first();

                if ($existing !== null) {
                    return $existing->id;
                }

                return CashSession::factory()->create([
                    'point_of_sale_id' => $posId,
                    'user_id' => $attributes['user_id'] ?? User::factory(),
                ])->id;
            },
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

    public function closedSession(): static
    {
        return $this->state(function (array $attributes): array {
            $pointOfSaleId = $attributes['point_of_sale_id'] ?? PointOfSale::factory();
            $userId = $attributes['user_id'] ?? User::factory();

            $session = CashSession::factory()->closed()->create([
                'point_of_sale_id' => $pointOfSaleId,
                'user_id' => $userId,
            ]);

            return [
                'point_of_sale_id' => $session->point_of_sale_id,
                'user_id' => $session->user_id,
                'cash_session_id' => $session->id,
            ];
        });
    }
}
