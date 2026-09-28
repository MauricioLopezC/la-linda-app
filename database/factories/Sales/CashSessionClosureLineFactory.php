<?php

namespace Database\Factories\Sales;

use App\Models\Sales\CashSession;
use App\Models\Sales\CashSessionClosureLine;
use App\Models\Sales\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSessionClosureLine>
 */
class CashSessionClosureLineFactory extends Factory
{
    protected $model = CashSessionClosureLine::class;

    /**
     * Define the model's default state: a closing without differences.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = number_format(fake()->randomFloat(2, 0, 100000), 2, '.', '');

        return [
            'cash_session_id' => CashSession::factory()->closed(),
            'payment_method_id' => PaymentMethod::factory()->cash(),
            'expected_amount' => $amount,
            'declared_amount' => $amount,
            'difference' => '0.00',
            'batch_reference' => null,
        ];
    }
}
