<?php

use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Catalog\Article;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\SupplierVoucherItem;
use App\Models\User;
use Database\Seeders\Purchasing\SupplierSeeder;
use Database\Seeders\Purchasing\SupplierVoucherSeeder;

test('supplier voucher seeder creates representative idempotent demo data', function () {
    User::factory()->create();
    Article::factory()->count(6)->create();
    $this->seed([SupplierSeeder::class, SupplierVoucherSeeder::class]);
    $this->seed(SupplierVoucherSeeder::class);

    expect(SupplierVoucher::query()->count())->toBe(7)
        ->and(SupplierVoucher::query()->where('type', SupplierVoucherType::Invoice)->count())->toBe(3)
        ->and(SupplierVoucher::query()->where('type', SupplierVoucherType::CreditNote)->count())->toBe(2)
        ->and(SupplierVoucher::query()->where('type', SupplierVoucherType::DebitNote)->count())->toBe(2)
        ->and(SupplierVoucherItem::query()->count())->toBe(21)
        ->and(SupplierVoucher::query()->withCount('items')->get()->every(
            fn (SupplierVoucher $voucher): bool => $voucher->items_count === 3
        ))->toBeTrue();

    expect(SupplierVoucher::query()->get()->contains(
        fn (SupplierVoucher $voucher): bool => $voucher->isOverdue()
    ))->toBeTrue();
});

test('the seeder demonstrates both a bound and a free credit note', function () {
    User::factory()->create();
    Article::factory()->count(6)->create();
    $this->seed([SupplierSeeder::class, SupplierVoucherSeeder::class]);
    $this->seed(SupplierVoucherSeeder::class);

    $boundNote = SupplierVoucher::query()
        ->where('type', SupplierVoucherType::CreditNote)
        ->where('number', '00000045')
        ->sole();
    $freeNote = SupplierVoucher::query()
        ->where('type', SupplierVoucherType::CreditNote)
        ->where('number', '00000321')
        ->sole();

    expect($boundNote->status)->toBe(SupplierVoucherStatus::Applied)
        ->and($boundNote->unappliedAmount())->toBe('0.00')
        ->and($freeNote->status)->toBe(SupplierVoucherStatus::PendingApplication)
        ->and($freeNote->unappliedAmount())->toBe('10000.00');

    $this->assertDatabaseCount('voucher_applications', 1);

    $reducedInvoice = SupplierVoucher::query()
        ->where('type', SupplierVoucherType::Invoice)
        ->where('number', '00012001')
        ->sole();

    expect($reducedInvoice->pendingBalance())->toBe('110000.00')
        ->and($reducedInvoice->status)->toBe(SupplierVoucherStatus::PartiallyPaid);
});
