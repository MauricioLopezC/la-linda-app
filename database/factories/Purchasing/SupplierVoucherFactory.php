<?php

namespace Database\Factories\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierVoucher>
 */
class SupplierVoucherFactory extends Factory
{
    use ConvertsMoneyToCents;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $netCents = fake()->numberBetween(10_000, 5_000_000);
        $vatCents = (int) round($netCents * 0.21);
        $otherTaxesCents = fake()->numberBetween(0, 50_000);

        return [
            'supplier_id' => Supplier::factory(),
            'type' => SupplierVoucherType::Invoice,
            'letter' => SupplierVoucherLetter::A,
            'point_of_sale' => fake()->numerify('####'),
            'number' => fake()->unique()->numerify('########'),
            'issue_date' => fake()->dateTimeBetween('-60 days', 'now'),
            'due_date' => fake()->optional(0.8)->dateTimeBetween('now', '+60 days'),
            'net_amount' => $this->centsToMoney($netCents),
            'vat_amount' => $this->centsToMoney($vatCents),
            'other_taxes_amount' => $this->centsToMoney($otherTaxesCents),
            'total_amount' => $this->centsToMoney($netCents + $vatCents + $otherTaxesCents),
            'status' => SupplierVoucherStatus::Pending,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function invoice(): static
    {
        return $this->state(fn (): array => [
            'type' => SupplierVoucherType::Invoice,
            'status' => SupplierVoucherStatus::Pending,
        ]);
    }

    public function creditNote(): static
    {
        return $this->state(fn (): array => [
            'type' => SupplierVoucherType::CreditNote,
            'status' => SupplierVoucherStatus::PendingApplication,
            'due_date' => null,
        ]);
    }

    public function debitNote(): static
    {
        return $this->state(fn (): array => [
            'type' => SupplierVoucherType::DebitNote,
            'status' => SupplierVoucherStatus::PendingApplication,
            'due_date' => null,
        ]);
    }

    public function overdue(): static
    {
        return $this->invoice()->state(fn (): array => [
            'issue_date' => now()->subDays(45)->toDateString(),
            'due_date' => now()->subDays(15)->toDateString(),
        ]);
    }
}
