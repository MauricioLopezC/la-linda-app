<?php

use App\Actions\Purchasing\AssociateCreditNoteToInvoice;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * @param  'creditNote'|'debitNote'|'invoice'  $state
 */
function voucherFor(Supplier $supplier, string $state, string $total, ?SupplierVoucherStatus $status = null): SupplierVoucher
{
    return SupplierVoucher::factory()->{$state}()->create(array_filter([
        'supplier_id' => $supplier->id,
        'total_amount' => $total,
        'status' => $status,
    ]));
}

function associate(
    SupplierVoucher $creditNote,
    SupplierVoucher $invoice,
    string $amount,
    ?int $userId = null,
): void {
    app(AssociateCreditNoteToInvoice::class)->handle($creditNote, $invoice->id, $amount, $userId);
}

test('a full association lowers the invoice pending balance immediately', function () {
    $supplier = Supplier::factory()->create();
    $invoice = voucherFor($supplier, 'invoice', '1000.00');
    $creditNote = voucherFor($supplier, 'creditNote', '400.00');

    associate($creditNote, $invoice, '400.00', User::factory()->create()->id);

    expect($invoice->fresh()->pendingBalance())->toBe('600.00')
        ->and($creditNote->fresh()->unappliedAmount())->toBe('0.00');
});

test('a partial association leaves the credit note remainder available', function () {
    $supplier = Supplier::factory()->create();
    $invoice = voucherFor($supplier, 'invoice', '1000.00');
    $creditNote = voucherFor($supplier, 'creditNote', '400.00');

    associate($creditNote, $invoice, '250.00', User::factory()->create()->id);

    expect($invoice->fresh()->pendingBalance())->toBe('750.00')
        ->and($creditNote->fresh()->unappliedAmount())->toBe('150.00');
});

test('both vouchers have their status re-derived from the imputation', function () {
    $supplier = Supplier::factory()->create();
    $invoice = voucherFor($supplier, 'invoice', '1000.00');
    $creditNote = voucherFor($supplier, 'creditNote', '400.00');

    associate($creditNote, $invoice, '400.00', User::factory()->create()->id);

    expect($invoice->fresh()->status)->toBe(SupplierVoucherStatus::PartiallyPaid)
        ->and($creditNote->fresh()->status)->toBe(SupplierVoucherStatus::Applied);
});

test('the imputation records the responsible user and its creation moment', function () {
    Carbon::setTestNow('2026-09-08 10:15:00');
    $supplier = Supplier::factory()->create();
    $invoice = voucherFor($supplier, 'invoice', '1000.00');
    $creditNote = voucherFor($supplier, 'creditNote', '400.00');
    $user = User::factory()->create();

    associate($creditNote, $invoice, '400.00', $user->id);

    $this->assertDatabaseHas('voucher_applications', [
        'source_voucher_id' => $creditNote->id,
        'target_voucher_id' => $invoice->id,
        'amount' => '400.00',
        'user_id' => $user->id,
        'created_at' => '2026-09-08 10:15:00',
    ]);

    Carbon::setTestNow();
});

test('the applied amount cannot exceed the invoice pending balance', function () {
    $supplier = Supplier::factory()->create();
    $invoice = voucherFor($supplier, 'invoice', '300.00');
    $creditNote = voucherFor($supplier, 'creditNote', '500.00');

    expect(fn () => associate($creditNote, $invoice, '400.00', User::factory()->create()->id))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('voucher_applications', 0);
});

test('the applied amount cannot exceed the credit note available amount', function () {
    $supplier = Supplier::factory()->create();
    $invoice = voucherFor($supplier, 'invoice', '1000.00');
    $creditNote = voucherFor($supplier, 'creditNote', '200.00');

    expect(fn () => associate($creditNote, $invoice, '250.00', User::factory()->create()->id))
        ->toThrow(ValidationException::class);
});

test('the credit note remainder shrinks with each association', function () {
    $supplier = Supplier::factory()->create();
    $firstInvoice = voucherFor($supplier, 'invoice', '1000.00');
    $secondInvoice = voucherFor($supplier, 'invoice', '1000.00');
    $creditNote = voucherFor($supplier, 'creditNote', '500.00');
    $user = User::factory()->create();

    associate($creditNote, $firstInvoice, '300.00', $user->id);

    expect(fn () => associate($creditNote->fresh(), $secondInvoice, '250.00', $user->id))
        ->toThrow(ValidationException::class);

    associate($creditNote->fresh(), $secondInvoice, '200.00', $user->id);

    expect($creditNote->fresh()->unappliedAmount())->toBe('0.00')
        ->and($creditNote->fresh()->status)->toBe(SupplierVoucherStatus::Applied);
});

test('the invoice must belong to the same supplier as the credit note', function () {
    $invoice = voucherFor(Supplier::factory()->create(), 'invoice', '1000.00');
    $creditNote = voucherFor(Supplier::factory()->create(), 'creditNote', '400.00');

    expect(fn () => associate($creditNote, $invoice, '400.00', User::factory()->create()->id))
        ->toThrow(ValidationException::class);
});

test('the target voucher must be an invoice', function () {
    $supplier = Supplier::factory()->create();
    $debitNote = voucherFor($supplier, 'debitNote', '1000.00');
    $creditNote = voucherFor($supplier, 'creditNote', '400.00');

    expect(fn () => associate($creditNote, $debitNote, '400.00', User::factory()->create()->id))
        ->toThrow(ValidationException::class);
});

test('the source voucher must be a credit note', function () {
    $supplier = Supplier::factory()->create();
    $invoice = voucherFor($supplier, 'invoice', '1000.00');
    $anotherInvoice = voucherFor($supplier, 'invoice', '400.00');

    expect(fn () => associate($anotherInvoice, $invoice, '400.00', User::factory()->create()->id))
        ->toThrow(ValidationException::class);
});

test('an annulled invoice or credit note cannot be associated', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create()->id;

    $cancelledInvoice = voucherFor($supplier, 'invoice', '1000.00', SupplierVoucherStatus::Cancelled);
    $creditNote = voucherFor($supplier, 'creditNote', '400.00');
    expect(fn () => associate($creditNote, $cancelledInvoice, '400.00', $user))
        ->toThrow(ValidationException::class);

    $invoice = voucherFor($supplier, 'invoice', '1000.00');
    $cancelledNote = voucherFor($supplier, 'creditNote', '400.00', SupplierVoucherStatus::Cancelled);
    expect(fn () => associate($cancelledNote, $invoice, '400.00', $user))
        ->toThrow(ValidationException::class);
});

test('an association without a resolvable responsible user is a misuse', function () {
    $supplier = Supplier::factory()->create();
    $invoice = voucherFor($supplier, 'invoice', '1000.00');
    $creditNote = voucherFor($supplier, 'creditNote', '400.00');

    expect(fn () => associate($creditNote, $invoice, '400.00'))
        ->toThrow(LogicException::class);
});
