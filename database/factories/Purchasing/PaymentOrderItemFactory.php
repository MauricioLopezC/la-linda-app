<?php

namespace Database\Factories\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Models\Purchasing\PaymentOrder;
use App\Models\Purchasing\PaymentOrderItem;
use App\Models\Purchasing\SupplierVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentOrderItem>
 */
class PaymentOrderItemFactory extends Factory
{
    use ConvertsMoneyToCents;

    protected $model = PaymentOrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_order_id' => PaymentOrder::factory(),
            'supplier_voucher_id' => SupplierVoucher::factory()->invoice(),
            'amount_applied' => $this->centsToMoney(fake()->numberBetween(1_000, 500_000)),
        ];
    }

    public function forInvoice(SupplierVoucher $invoice, string $amount): static
    {
        return $this->state(fn (): array => [
            'supplier_voucher_id' => $invoice->id,
            'amount_applied' => $amount,
        ]);
    }
}
