<?php

namespace Database\Factories\Purchasing;

use App\Models\Purchasing\PaymentOrder;
use App\Models\Purchasing\PaymentOrderMethod;
use App\Models\Sales\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentOrderMethod>
 */
class PaymentOrderMethodFactory extends Factory
{
    protected $model = PaymentOrderMethod::class;

    public function definition(): array
    {
        return [
            'payment_order_id' => PaymentOrder::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'amount' => '2000.00',
        ];
    }
}
