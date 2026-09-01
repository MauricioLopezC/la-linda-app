<?php

use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Purchasing\SupplierVoucher;
use Database\Seeders\Purchasing\SupplierSeeder;
use Database\Seeders\Purchasing\SupplierVoucherSeeder;

test('supplier voucher seeder creates representative idempotent demo data', function () {
    $this->seed([SupplierSeeder::class, SupplierVoucherSeeder::class]);
    $this->seed(SupplierVoucherSeeder::class);

    expect(SupplierVoucher::query()->count())->toBe(4)
        ->and(SupplierVoucher::query()->where('type', SupplierVoucherType::Invoice)->count())->toBe(2)
        ->and(SupplierVoucher::query()->where('type', SupplierVoucherType::CreditNote)->count())->toBe(1)
        ->and(SupplierVoucher::query()->where('type', SupplierVoucherType::DebitNote)->count())->toBe(1)
        ->and(SupplierVoucher::query()->where('status', SupplierVoucherStatus::Pending)->count())->toBe(2)
        ->and(SupplierVoucher::query()->where('status', SupplierVoucherStatus::PendingApplication)->count())->toBe(2);

    expect(SupplierVoucher::query()->get()->contains(
        fn (SupplierVoucher $voucher): bool => $voucher->isOverdue()
    ))->toBeTrue();
});
