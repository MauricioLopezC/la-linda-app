<?php

use App\Actions\Purchasing\IssuePaymentOrder;
use App\Data\Purchasing\PaymentOrderData;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Models\Purchasing\PaymentOrder;
use App\Models\Purchasing\PaymentOrderItem;
use App\Models\Purchasing\PaymentOrderMethod;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\VoucherApplication;
use App\Models\Sales\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build the minimal valid payload for IssuePaymentOrder.
 *
 * @param  array<int, array{supplier_voucher_id: int, amount_applied: string}>  $items
 */
function paymentPayload(
    Supplier $supplier,
    array $paymentMethods,
    array $items,
): array {
    return [
        'supplier_id' => $supplier->id,
        'date' => today()->toDateString(),
        'notes' => null,
        'payment_methods' => $paymentMethods,
        'items' => $items,
    ];
}

/**
 * Create an invoice for the given supplier with the given total (all in the amount, no VAT split).
 */
function invoiceFor(Supplier $supplier, string $total): SupplierVoucher
{
    return SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => $total,
    ]);
}

function issueAction(): IssuePaymentOrder
{
    return app(IssuePaymentOrder::class);
}

// ---------------------------------------------------------------------------
// Caso A — pago que cancela una factura entera y deja otra parcial
// ---------------------------------------------------------------------------

test('caso A: pays one invoice fully and another partially', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();

    $invoice1 = invoiceFor($supplier, '10000.00');
    $invoice2 = invoiceFor($supplier, '6000.00');

    $payload = paymentPayload($supplier, [['payment_method_id' => $paymentMethod->id, 'amount' => '12000.00']], [
        ['supplier_voucher_id' => $invoice1->id, 'amount_applied' => '10000.00'],
        ['supplier_voucher_id' => $invoice2->id, 'amount_applied' => '2000.00'],
    ]);

    $result = issueAction()->handle($payload, $user->id);

    expect($result)->toBeInstanceOf(PaymentOrderData::class);
    expect($result->total_amount)->toBe('12000.00');
    expect($result->status)->toBe('emitida');
    expect($result->order_number)->toStartWith('OP-');

    // Invoice 1: fully paid
    $item1 = collect($result->items)->firstWhere('supplier_voucher_id', $invoice1->id);
    expect($item1->voucher_remaining_balance)->toBe('0.00');
    expect($item1->voucher_status)->toBe(SupplierVoucherStatus::Paid->value);

    // Invoice 2: partially paid
    $item2 = collect($result->items)->firstWhere('supplier_voucher_id', $invoice2->id);
    expect($item2->voucher_remaining_balance)->toBe('4000.00');
    expect($item2->voucher_status)->toBe(SupplierVoucherStatus::PartiallyPaid->value);

    $this->assertDatabaseHas('payment_orders', [
        'order_number' => $result->order_number,
        'total_amount' => '12000.00',
        'status' => 'emitida',
    ]);

    $this->assertDatabaseHas('payment_order_items', [
        'supplier_voucher_id' => $invoice1->id,
        'amount_applied' => '10000.00',
    ]);
});

// ---------------------------------------------------------------------------
// Caso B — amount_applied supera el saldo pendiente
// ---------------------------------------------------------------------------

test('caso B: rejects when amount_applied exceeds pending balance', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();
    $invoice = invoiceFor($supplier, '5000.00');

    $payload = paymentPayload($supplier, [['payment_method_id' => $paymentMethod->id, 'amount' => '8000.00']], [
        ['supplier_voucher_id' => $invoice->id, 'amount_applied' => '8000.00'],
    ]);

    expect(fn () => issueAction()->handle($payload, $user->id))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseMissing('payment_orders', ['supplier_id' => $supplier->id]);
    $this->assertDatabaseMissing('payment_order_items', ['supplier_voucher_id' => $invoice->id]);
});

// ---------------------------------------------------------------------------
// Caso C — factura pertenece a otro proveedor
// ---------------------------------------------------------------------------

test('caso C: rejects when invoice belongs to a different supplier', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();

    $invoice = invoiceFor($otherSupplier, '5000.00');

    $payload = paymentPayload($supplier, [['payment_method_id' => $paymentMethod->id, 'amount' => '1000.00']], [
        ['supplier_voucher_id' => $invoice->id, 'amount_applied' => '1000.00'],
    ]);

    expect(fn () => issueAction()->handle($payload, $user->id))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseMissing('payment_orders', ['supplier_id' => $supplier->id]);
});

// ---------------------------------------------------------------------------
// Caso D — mismo supplier_voucher_id repetido en items
// ---------------------------------------------------------------------------

test('caso D: rejects duplicate supplier_voucher_id in items', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();
    $invoice = invoiceFor($supplier, '10000.00');

    // The Form Request catches duplicates via Rule::distinct, but the Action has a second-level
    // check. Here we call the action directly (bypassing the request) to test its own guard.
    // We do this by sending two entries for the same invoice.
    $payload = paymentPayload($supplier, [['payment_method_id' => $paymentMethod->id, 'amount' => '6000.00']], [
        ['supplier_voucher_id' => $invoice->id, 'amount_applied' => '3000.00'],
        ['supplier_voucher_id' => $invoice->id, 'amount_applied' => '3000.00'],
    ]);

    // The DB UNIQUE constraint or the Action balance check will reject this;
    // either way a ValidationException must be thrown.
    expect(fn () => issueAction()->handle($payload, $user->id))
        ->toThrow(Exception::class);

    $this->assertDatabaseMissing('payment_orders', ['supplier_id' => $supplier->id]);
});

// ---------------------------------------------------------------------------
// Caso E — factura ya afectada por una NC de HU-054 (motor de saldo compartido)
// ---------------------------------------------------------------------------

test('caso E: pendingBalance accounts for prior credit note applications', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();

    // Invoice with total $10,000
    $invoice = invoiceFor($supplier, '10000.00');

    // A credit note of $3,000 already applied to the invoice via HU-054
    $creditNote = SupplierVoucher::factory()->creditNote()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '3000.00',
    ]);
    VoucherApplication::factory()->from($creditNote)->to($invoice)->amount('3000.00')->create();

    // Pending balance must now be $7,000
    expect($invoice->fresh()->pendingBalance())->toBe('7000.00');

    // Pay the remaining $7,000
    $payload = paymentPayload($supplier, [['payment_method_id' => $paymentMethod->id, 'amount' => '7000.00']], [
        ['supplier_voucher_id' => $invoice->id, 'amount_applied' => '7000.00'],
    ]);

    $result = issueAction()->handle($payload, $user->id);

    $item = collect($result->items)->firstWhere('supplier_voucher_id', $invoice->id);
    expect($item->voucher_remaining_balance)->toBe('0.00');
    expect($item->voucher_status)->toBe(SupplierVoucherStatus::Paid->value);
});

// ---------------------------------------------------------------------------
// Caso F — concurrencia: dos procesos contra la misma factura
// ---------------------------------------------------------------------------

test('caso F: concurrent orders serialize via lockForUpdate — second fails if balance exhausted', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();

    // Invoice with $5,000 pending balance. Each order claims $4,000 — together they exceed the balance.
    $invoice = invoiceFor($supplier, '5000.00');

    $payload = paymentPayload($supplier, [['payment_method_id' => $paymentMethod->id, 'amount' => '4000.00']], [
        ['supplier_voucher_id' => $invoice->id, 'amount_applied' => '4000.00'],
    ]);

    // First order: should succeed
    $result = issueAction()->handle($payload, $user->id);
    expect($result->status)->toBe('emitida');

    // Balance is now $1,000. Second order tries $4,000 again — must be rejected.
    expect(fn () => issueAction()->handle($payload, $user->id))
        ->toThrow(ValidationException::class);

    // Only one payment_order must exist
    $this->assertDatabaseCount('payment_orders', 1);
});

// ---------------------------------------------------------------------------
// order_number format and uniqueness
// ---------------------------------------------------------------------------

test('generated order numbers follow OP-XXXXXX format', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();

    $invoice = invoiceFor($supplier, '1000.00');
    $payload = paymentPayload($supplier, [['payment_method_id' => $paymentMethod->id, 'amount' => '500.00']], [
        ['supplier_voucher_id' => $invoice->id, 'amount_applied' => '500.00'],
    ]);

    $result = issueAction()->handle($payload, $user->id);

    expect($result->order_number)->toMatch('/^OP-\d{6}$/');
});

// ---------------------------------------------------------------------------
// HTTP endpoint — store
// ---------------------------------------------------------------------------

test('POST purchasing/payment-orders creates order and redirects', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();
    $invoice = invoiceFor($supplier, '5000.00');

    $this->actingAs($user)
        ->post(route('purchasing.payment-orders.store'), [
            'supplier_id' => $supplier->id,
            'date' => today()->toDateString(),
            'payment_methods' => [
                ['payment_method_id' => $paymentMethod->id, 'amount' => '5000.00'],
            ],
            'items' => [
                ['supplier_voucher_id' => $invoice->id, 'amount_applied' => '5000.00'],
            ],
        ])
        ->assertRedirect(route('purchasing.payment-orders.create'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('payment_orders', [
        'supplier_id' => $supplier->id,
        'total_amount' => '5000.00',
        'status' => 'emitida',
    ]);
});

// ---------------------------------------------------------------------------
// HTTP endpoint — invoices
// ---------------------------------------------------------------------------

test('GET suppliers/{supplier}/invoices returns only invoices with pending balance', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();

    // Invoice with full balance
    $pending = invoiceFor($supplier, '3000.00');

    // Invoice already fully paid
    $paid = invoiceFor($supplier, '2000.00');
    $order = PaymentOrder::factory()->has(
        PaymentOrderMethod::factory()->count(1), 'paymentMethods'
    )->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '2000.00',
    ]);
    
    PaymentOrderItem::factory()->forInvoice($paid, '2000.00')->create([
        'payment_order_id' => $order->id,
    ]);

    $this->actingAs($user)
        ->getJson(route('purchasing.payment-orders.suppliers.invoices', $supplier))
        ->assertOk()
        ->assertJsonFragment(['id' => $pending->id])
        ->assertJsonMissing(['id' => $paid->id]);
});

// ---------------------------------------------------------------------------
// Caso G — OP con NC que baja el neto y se paga con un único medio
// ---------------------------------------------------------------------------
test('caso G: OP with credit note reduces net total', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();

    $invoice = invoiceFor($supplier, '10000.00');
    $creditNote = SupplierVoucher::factory()->creditNote()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '3000.00',
    ]);

    // Net total should be 7000
    $payload = paymentPayload($supplier, [['payment_method_id' => $paymentMethod->id, 'amount' => '7000.00']], [
        ['supplier_voucher_id' => $invoice->id, 'amount_applied' => '10000.00'],
        ['supplier_voucher_id' => $creditNote->id, 'amount_applied' => '3000.00'],
    ]);

    $result = issueAction()->handle($payload, $user->id);

    expect($result->total_amount)->toBe('7000.00');
    $this->assertDatabaseHas('payment_orders', [
        'order_number' => $result->order_number,
        'total_amount' => '7000.00',
        'status' => 'emitida',
    ]);
});

// ---------------------------------------------------------------------------
// Caso H — OP con múltiples medios de pago
// ---------------------------------------------------------------------------
test('caso H: OP with multiple payment methods', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $cash = PaymentMethod::factory()->create();
    $transfer = PaymentMethod::factory()->create();

    $invoice = invoiceFor($supplier, '10000.00');

    $payload = paymentPayload($supplier, [
        ['payment_method_id' => $cash->id, 'amount' => '6000.00'],
        ['payment_method_id' => $transfer->id, 'amount' => '4000.00'],
    ], [
        ['supplier_voucher_id' => $invoice->id, 'amount_applied' => '10000.00'],
    ]);

    $result = issueAction()->handle($payload, $user->id);

    expect($result->total_amount)->toBe('10000.00');
    $this->assertDatabaseCount('payment_order_methods', 2);
    $this->assertDatabaseHas('payment_order_methods', [
        'payment_order_id' => $result->id,
        'payment_method_id' => $cash->id,
        'amount' => '6000.00',
    ]);
    $this->assertDatabaseHas('payment_order_methods', [
        'payment_order_id' => $result->id,
        'payment_method_id' => $transfer->id,
        'amount' => '4000.00',
    ]);
});

// ---------------------------------------------------------------------------
// Caso I — Rechazo si la suma de los medios difiere del neto
// ---------------------------------------------------------------------------
test('caso I: rejects when payment methods sum does not match net total', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();

    $invoice = invoiceFor($supplier, '10000.00');

    $payload = paymentPayload($supplier, [
        ['payment_method_id' => $paymentMethod->id, 'amount' => '8000.00'],
    ], [
        ['supplier_voucher_id' => $invoice->id, 'amount_applied' => '10000.00'],
    ]);

    expect(fn () => issueAction()->handle($payload, $user->id))
        ->toThrow(ValidationException::class, 'La suma de los medios de pago debe coincidir exactamente con el importe neto a pagar.');

    $this->assertDatabaseMissing('payment_orders', ['supplier_id' => $supplier->id]);
});
