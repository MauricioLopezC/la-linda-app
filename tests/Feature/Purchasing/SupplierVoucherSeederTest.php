<?php

use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Catalog\Article;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\SupplierVoucherItem;
use Database\Seeders\Purchasing\SupplierSeeder;
use Database\Seeders\Purchasing\SupplierVoucherSeeder;

test('supplier voucher seeder creates representative idempotent demo data', function () {
    Article::factory()->count(6)->create();
    $this->seed([SupplierSeeder::class, SupplierVoucherSeeder::class]);
    $this->seed(SupplierVoucherSeeder::class);

    expect(SupplierVoucher::query()->count())->toBe(6)
        ->and(SupplierVoucher::query()->where('type', SupplierVoucherType::Invoice)->count())->toBe(3)
        ->and(SupplierVoucher::query()->where('type', SupplierVoucherType::CreditNote)->count())->toBe(1)
        ->and(SupplierVoucher::query()->where('type', SupplierVoucherType::DebitNote)->count())->toBe(2)
        ->and(SupplierVoucher::query()->where('status', SupplierVoucherStatus::Pending)->count())->toBe(5)
        ->and(SupplierVoucher::query()->where('status', SupplierVoucherStatus::PendingApplication)->count())->toBe(1)
        ->and(SupplierVoucherItem::query()->count())->toBe(18)
        ->and(SupplierVoucher::query()->withCount('items')->get()->every(
            fn (SupplierVoucher $voucher): bool => $voucher->items_count === 3
        ))->toBeTrue();

    expect(SupplierVoucher::query()->get()->contains(
        fn (SupplierVoucher $voucher): bool => $voucher->isOverdue()
    ))->toBeTrue();
});
