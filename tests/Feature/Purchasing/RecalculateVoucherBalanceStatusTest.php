<?php

use App\Actions\Purchasing\RecalculateVoucherBalanceStatus;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Models\Purchasing\PaymentOrderItem;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\VoucherApplication;

/**
 * @param  'creditNote'|'debitNote'|'invoice'  $state
 */
function statusVoucher(string $state, string $total, SupplierVoucherStatus $status): SupplierVoucher
{
    return SupplierVoucher::factory()->{$state}()->create([
        'net_amount' => $total,
        'vat_amount' => '0.00',
        'other_taxes_amount' => '0.00',
        'total_amount' => $total,
        'status' => $status,
    ]);
}

function recalculate(SupplierVoucher $voucher): SupplierVoucher
{
    return app(RecalculateVoucherBalanceStatus::class)->handle($voucher);
}

test('an invoice with a partial payment becomes partially paid', function () {
    $invoice = statusVoucher('invoice', '1000.00', SupplierVoucherStatus::Pending);
    PaymentOrderItem::factory()->forInvoice($invoice, '400.00')->create();

    expect(recalculate($invoice)->status)->toBe(SupplierVoucherStatus::PartiallyPaid);

    $this->assertDatabaseHas('supplier_vouchers', [
        'id' => $invoice->id,
        'status' => SupplierVoucherStatus::PartiallyPaid->value,
    ]);
});

test('an invoice paid to zero becomes paid', function () {
    $invoice = statusVoucher('invoice', '1000.00', SupplierVoucherStatus::Pending);
    PaymentOrderItem::factory()->forInvoice($invoice, '1000.00')->create();

    expect(recalculate($invoice)->status)->toBe(SupplierVoucherStatus::Paid);
});

test('a paid invoice reopened by a debit note is no longer paid', function () {
    $invoice = statusVoucher('invoice', '1000.00', SupplierVoucherStatus::Pending);
    PaymentOrderItem::factory()->forInvoice($invoice, '1000.00')->create();
    recalculate($invoice);
    expect($invoice->fresh()->status)->toBe(SupplierVoucherStatus::Paid);

    $debitNote = statusVoucher('debitNote', '300.00', SupplierVoucherStatus::PendingApplication);
    VoucherApplication::factory()->from($debitNote)->to($invoice)->amount('300.00')->create();

    expect(recalculate($invoice->fresh())->status)->toBe(SupplierVoucherStatus::PartiallyPaid);
});

test('a credit note becomes partially applied then applied as it is imputed', function () {
    $creditNote = statusVoucher('creditNote', '500.00', SupplierVoucherStatus::PendingApplication);
    $invoice = statusVoucher('invoice', '1000.00', SupplierVoucherStatus::Pending);

    VoucherApplication::factory()->from($creditNote)->to($invoice)->amount('200.00')->create();
    expect(recalculate($creditNote)->status)->toBe(SupplierVoucherStatus::PartiallyApplied);

    VoucherApplication::factory()->from($creditNote)->to($invoice)->amount('300.00')->create();
    expect(recalculate($creditNote->fresh())->status)->toBe(SupplierVoucherStatus::Applied);
});

test('a cancelled voucher is left untouched', function () {
    $invoice = statusVoucher('invoice', '1000.00', SupplierVoucherStatus::Cancelled);
    PaymentOrderItem::factory()->forInvoice($invoice, '1000.00')->create();

    expect(recalculate($invoice)->status)->toBe(SupplierVoucherStatus::Cancelled);

    $this->assertDatabaseHas('supplier_vouchers', [
        'id' => $invoice->id,
        'status' => SupplierVoucherStatus::Cancelled->value,
    ]);
});
