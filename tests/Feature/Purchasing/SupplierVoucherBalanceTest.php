<?php

use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Purchasing\PaymentOrder;
use App\Models\Purchasing\PaymentOrderItem;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\VoucherApplication;
use Illuminate\Support\Facades\DB;

/**
 * @param  'creditNote'|'debitNote'|'invoice'  $state
 */
function balanceVoucher(string $state, string $total): SupplierVoucher
{
    return SupplierVoucher::factory()->{$state}()->create([
        'total_amount' => $total,
    ]);
}

function imputeNote(SupplierVoucher $note, SupplierVoucher $invoice, string $amount): void
{
    VoucherApplication::factory()->from($note)->to($invoice)->amount($amount)->create();
}

test('an invoice pending balance equals its total when nothing has been imputed', function () {
    $invoice = balanceVoucher('invoice', '1000.00');

    expect($invoice->pendingBalance())->toBe('1000.00')
        ->and($invoice->outstandingAmount())->toBe('1000.00');
});

test('a payment order line lowers the invoice pending balance', function () {
    $invoice = balanceVoucher('invoice', '1000.00');
    PaymentOrderItem::factory()->forInvoice($invoice, '250.00')->create();

    expect($invoice->fresh()->pendingBalance())->toBe('750.00');
});

test('a credit note application lowers the invoice pending balance', function () {
    $invoice = balanceVoucher('invoice', '1000.00');
    imputeNote(balanceVoucher('creditNote', '400.00'), $invoice, '400.00');

    expect($invoice->fresh()->pendingBalance())->toBe('600.00');
});

test('credit notes and payments combine on the same invoice', function () {
    $invoice = balanceVoucher('invoice', '1000.00');
    imputeNote(balanceVoucher('creditNote', '300.00'), $invoice, '300.00');
    PaymentOrderItem::factory()->forInvoice($invoice, '200.00')->create();

    expect($invoice->fresh()->pendingBalance())->toBe('500.00');
});

test('a debit note has its own payable balance', function () {
    $debitNote = balanceVoucher('debitNote', '150.00');
    PaymentOrderItem::factory()->forInvoice($debitNote, '50.00')->create();

    expect($debitNote->fresh()->pendingBalance())->toBe('100.00')
        ->and($debitNote->fresh()->outstandingAmount())->toBe('100.00');
});

test('a cancelled payment order does not lower the payable balance', function () {
    $invoice = balanceVoucher('invoice', '1000.00');
    $cancelledOrder = PaymentOrder::factory()->cancelled()->create();
    PaymentOrderItem::factory()->forInvoice($invoice, '250.00')->create([
        'payment_order_id' => $cancelledOrder->id,
    ]);

    expect($invoice->fresh()->pendingBalance())->toBe('1000.00');
});

test('a note reports how much of its amount is still unapplied', function () {
    $creditNote = balanceVoucher('creditNote', '500.00');
    imputeNote($creditNote, balanceVoucher('invoice', '1000.00'), '150.00');
    imputeNote($creditNote, balanceVoucher('invoice', '1000.00'), '200.00');

    expect($creditNote->fresh()->unappliedAmount())->toBe('150.00')
        ->and($creditNote->fresh()->outstandingAmount())->toBe('150.00');
});

test('outstanding amount follows the voucher type', function () {
    $invoice = balanceVoucher('invoice', '1000.00');
    PaymentOrderItem::factory()->forInvoice($invoice, '1000.00')->create();
    $note = balanceVoucher('creditNote', '500.00');

    expect($invoice->fresh()->outstandingAmount())->toBe('0.00')
        ->and($note->fresh()->outstandingAmount())->toBe('500.00');
});

test('the balance aggregate scope resolves every balance in a single query', function () {
    $paid = balanceVoucher('invoice', '1000.00');
    PaymentOrderItem::factory()->forInvoice($paid, '400.00')->create();

    $adjusted = balanceVoucher('invoice', '2000.00');
    imputeNote(balanceVoucher('creditNote', '500.00'), $adjusted, '500.00');

    balanceVoucher('invoice', '300.00');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $invoices = SupplierVoucher::query()
        ->withBalanceAggregates()
        ->where('type', SupplierVoucherType::Invoice->value)
        ->orderBy('id')
        ->get();

    $balances = $invoices->map->pendingBalance()->all();

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and($balances)->toBe(['600.00', '1500.00', '300.00']);

    DB::disableQueryLog();
});
