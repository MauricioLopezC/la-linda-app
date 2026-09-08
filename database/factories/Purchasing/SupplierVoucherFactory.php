<?php

namespace Database\Factories\Purchasing;

use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupplierVoucher> */
class SupplierVoucherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'type' => SupplierVoucherType::Invoice,
            'letter' => SupplierVoucherLetter::A,
            'point_of_sale' => fake()->numerify('####'),
            'number' => fake()->unique()->numerify('########'),
            'issue_date' => fake()->dateTimeBetween('-60 days', 'now'),
            'due_date' => fake()->optional(0.8)->dateTimeBetween('now', '+60 days'),
            'total_amount' => fake()->randomFloat(2, 100, 5_000_000),
            'status' => SupplierVoucherStatus::Pending,
            'notes' => fake()->optional()->sentence(),
            'annulled_at' => null,
            'annulled_by' => null,
            'annulment_reason' => null,
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
            'status' => SupplierVoucherStatus::Pending,
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
