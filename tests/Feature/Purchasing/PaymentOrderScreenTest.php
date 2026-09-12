<?php

use App\Data\Purchasing\PaymentOrderData;
use App\Models\Purchasing\PaymentOrder;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Sales\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Sub-flujo A (Clara) — pantalla de emisión de orden de pago. La lógica de la Action está
 * cubierta por IssuePaymentOrderTest (Azael); acá se prueba el glue de la pantalla: props del
 * formulario, forma del payload que arma el front (contrato §2), y el flash del PaymentOrderData
 * que alimenta el panel de éxito.
 */
function invoiceWithBalance(Supplier $supplier, string $total): SupplierVoucher
{
    return SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => $total,
    ]);
}

test('the create screen lists only active suppliers and active payment methods', function () {
    Supplier::factory()->create(['is_active' => true, 'business_name' => 'Activo SA']);
    Supplier::factory()->create(['is_active' => false, 'business_name' => 'Inactivo SA']);
    PaymentMethod::factory()->create(['is_active' => true]);
    PaymentMethod::factory()->create(['is_active' => false]);

    $this->actingAs(User::factory()->create())
        ->get(route('purchasing.payment-orders.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/payment-orders/create')
            ->has('suppliers', 1)
            ->where('suppliers.0.business_name', 'Activo SA')
            ->has('paymentMethods', 1)
            ->has('today')
        );
});

test('the invoices endpoint returns only that supplier invoices with a pending balance', function () {
    $supplier = Supplier::factory()->create();
    $other = Supplier::factory()->create();

    $pending = invoiceWithBalance($supplier, '10000.00');
    invoiceWithBalance($other, '5000.00');

    $this->actingAs(User::factory()->create())
        ->getJson(route('purchasing.payment-orders.suppliers.invoices', $supplier))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $pending->id)
        ->assertJsonPath('0.outstanding_amount', '10000.00');
});

test('store issues the order and flashes the PaymentOrderData for the success panel', function () {
    $supplier = Supplier::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();
    $invoice1 = invoiceWithBalance($supplier, '10000.00');
    $invoice2 = invoiceWithBalance($supplier, '6000.00');

    $response = $this->actingAs(User::factory()->create())
        ->post(route('purchasing.payment-orders.store'), [
            'supplier_id' => $supplier->id,
            'date' => today()->toDateString(),
            'notes' => null,
            'payment_methods' => [['payment_method_id' => $paymentMethod->id, 'amount' => '12000.00']],
            'items' => [
                ['supplier_voucher_id' => $invoice1->id, 'amount_applied' => '10000.00'],
                ['supplier_voucher_id' => $invoice2->id, 'amount_applied' => '2000.00'],
            ],
        ]);

    $response->assertRedirect(route('purchasing.payment-orders.create'));
    $response->assertSessionHas('success');

    // El panel de éxito lee flash.issuedOrder; Inertia lo serializa a la forma de PaymentOrderData.
    $issued = $response->getSession()->get('issuedOrder');
    $issuedArray = $issued instanceof PaymentOrderData ? $issued->toArray() : (array) $issued;
    expect($issuedArray['total_amount'])->toBe('12000.00');
    expect($issuedArray['order_number'])->toStartWith('OP-');
    expect($issuedArray['items'])->toHaveCount(2);
    expect($issuedArray['items'][0])->toHaveKeys([
        'supplier_voucher_id',
        'amount_applied',
        'voucher_remaining_balance',
        'voucher_status',
        'voucher_status_label',
    ]);

    expect(PaymentOrder::count())->toBe(1);
});

test('store rejects a duplicated invoice in items', function () {
    $supplier = Supplier::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();
    $invoice = invoiceWithBalance($supplier, '10000.00');

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.payment-orders.store'), [
            'supplier_id' => $supplier->id,
            'date' => today()->toDateString(),
            'payment_methods' => [['payment_method_id' => $paymentMethod->id, 'amount' => '3000.00']],
            'items' => [
                ['supplier_voucher_id' => $invoice->id, 'amount_applied' => '1000.00'],
                ['supplier_voucher_id' => $invoice->id, 'amount_applied' => '2000.00'],
            ],
        ])
        ->assertSessionHasErrors('items.0.supplier_voucher_id');

    expect(PaymentOrder::count())->toBe(0);
});

test('store rejects a non-positive amount', function () {
    $supplier = Supplier::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();
    $invoice = invoiceWithBalance($supplier, '10000.00');

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.payment-orders.store'), [
            'supplier_id' => $supplier->id,
            'date' => today()->toDateString(),
            'payment_methods' => [['payment_method_id' => $paymentMethod->id, 'amount' => '0.00']],
            'items' => [
                ['supplier_voucher_id' => $invoice->id, 'amount_applied' => '0'],
            ],
        ])
        ->assertSessionHasErrors('items.0.amount_applied');
});

test('store rejects a client-sent total_amount', function () {
    $supplier = Supplier::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();
    $invoice = invoiceWithBalance($supplier, '10000.00');

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.payment-orders.store'), [
            'supplier_id' => $supplier->id,
            'date' => today()->toDateString(),
            'payment_methods' => [['payment_method_id' => $paymentMethod->id, 'amount' => '1000.00']],
            'total_amount' => '999.00',
            'items' => [
                ['supplier_voucher_id' => $invoice->id, 'amount_applied' => '1000.00'],
            ],
        ])
        ->assertSessionHasErrors('total_amount');
});
