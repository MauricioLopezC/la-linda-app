<?php

namespace Database\Factories\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\VoucherApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoucherApplication>
 */
class VoucherApplicationFactory extends Factory
{
    use ConvertsMoneyToCents;

    protected $model = VoucherApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_voucher_id' => SupplierVoucher::factory()->creditNote(),
            'target_voucher_id' => SupplierVoucher::factory()->invoice(),
            'amount' => $this->centsToMoney(fake()->numberBetween(1_000, 200_000)),
            'user_id' => User::factory(),
        ];
    }

    public function from(SupplierVoucher $note): static
    {
        return $this->state(fn (): array => ['source_voucher_id' => $note->id]);
    }

    public function to(SupplierVoucher $invoice): static
    {
        return $this->state(fn (): array => ['target_voucher_id' => $invoice->id]);
    }

    public function amount(string $amount): static
    {
        return $this->state(fn (): array => ['amount' => $amount]);
    }
}
