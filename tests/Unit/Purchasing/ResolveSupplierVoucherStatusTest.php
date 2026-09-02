<?php

use App\Actions\Purchasing\ResolveSupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;

test('status is resolved from type and derived pending balance', function (
    SupplierVoucherType $type,
    string $total,
    string $pendingBalance,
    SupplierVoucherStatus $expected,
) {
    $status = (new ResolveSupplierVoucherStatus)->handle($type, $total, $pendingBalance);

    expect($status)->toBe($expected);
})->with([
    'pending invoice' => [SupplierVoucherType::Invoice, '100.00', '100.00', SupplierVoucherStatus::Pending],
    'partially paid invoice' => [SupplierVoucherType::Invoice, '100.00', '40.00', SupplierVoucherStatus::PartiallyPaid],
    'paid invoice' => [SupplierVoucherType::Invoice, '100.00', '0.00', SupplierVoucherStatus::Paid],
    'credit note pending application' => [SupplierVoucherType::CreditNote, '100.00', '100.00', SupplierVoucherStatus::PendingApplication],
    'credit note partially applied' => [SupplierVoucherType::CreditNote, '100.00', '40.00', SupplierVoucherStatus::PartiallyApplied],
    'credit note applied' => [SupplierVoucherType::CreditNote, '100.00', '0.00', SupplierVoucherStatus::Applied],
    'debit note pending application' => [SupplierVoucherType::DebitNote, '100,00', '100,00', SupplierVoucherStatus::PendingApplication],
]);

test('status resolver rejects invalid or non-positive totals', function (string $total) {
    expect(fn () => (new ResolveSupplierVoucherStatus)->handle(
        SupplierVoucherType::Invoice,
        $total,
        '0.00',
    ))->toThrow(InvalidArgumentException::class);
})->with(['0.00', '-1.00', 'invalid']);
