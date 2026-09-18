<?php

use App\Enums\Purchasing\PaymentOrderStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Purchasing\PaymentOrder;
use App\Models\Purchasing\PaymentOrderItem;
use App\Models\Purchasing\PaymentOrderMethod;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Sales\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('unauthenticated users cannot view or export payment orders', function () {
    $this->get(route('purchasing.payment-orders.index'))->assertRedirect(route('login'));
    $this->get(route('purchasing.payment-orders.export.csv'))->assertRedirect(route('login'));
    $this->get(route('purchasing.payment-orders.export.excel'))->assertRedirect(route('login'));
});

test('renders index screen with paginated orders, filter options and total egresses', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['business_name' => 'Molinos Río']);
    $method = PaymentMethod::factory()->create(['name' => 'Transferencia']);

    $order = PaymentOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'date' => '2026-09-10',
        'total_amount' => '15000.50',
        'status' => PaymentOrderStatus::Issued,
    ]);

    PaymentOrderMethod::factory()->create([
        'payment_order_id' => $order->id,
        'payment_method_id' => $method->id,
        'amount' => '15000.50',
    ]);

    $voucher = SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
    ]);

    PaymentOrderItem::factory()->create([
        'payment_order_id' => $order->id,
        'supplier_voucher_id' => $voucher->id,
        'amount_applied' => '15000.50',
    ]);

    $this->actingAs($user)
        ->get(route('purchasing.payment-orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/payment-orders/index')
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $order->id)
            ->where('orders.data.0.supplier_name', 'Molinos Río')
            ->where('orders.data.0.total_amount', '15000.50')
            ->where('orders.data.0.items.0.supplier_voucher_id', $voucher->id)
            ->where('totalEgresses', '15000.50')
            ->has('suppliers')
            ->has('paymentMethods')
            ->has('voucherTypes')
            ->has('statuses')
            ->has('filters')
        );
});

test('total egresses excludes cancelled orders and unpaid vouchers', function () {
    $user = User::factory()->create();

    // Orden emitida
    PaymentOrder::factory()->create([
        'date' => '2026-09-15',
        'total_amount' => '25000.00',
        'status' => PaymentOrderStatus::Issued,
    ]);

    // Orden anulada (no debe sumar al total de egresos)
    PaymentOrder::factory()->cancelled()->create([
        'date' => '2026-09-15',
        'total_amount' => '10000.00',
    ]);

    // Comprobante impago sin orden de pago (no debe sumar a los egresos)
    SupplierVoucher::factory()->invoice()->create([
        'total_amount' => '80000.00',
    ]);

    $this->actingAs($user)
        ->get(route('purchasing.payment-orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('totalEgresses', '25000.00')
            ->has('orders.data', 2)
        );
});

test('verificación de criterio de aceptación: filtrar por proveedor y rango de fechas suma exactamente los pagos listados', function () {
    $user = User::factory()->create();
    $supplierA = Supplier::factory()->create();
    $supplierB = Supplier::factory()->create();

    // Orden 1: Proveedor A, dentro de fecha
    PaymentOrder::factory()->create([
        'supplier_id' => $supplierA->id,
        'date' => '2026-09-05',
        'total_amount' => '12345.50',
        'status' => PaymentOrderStatus::Issued,
    ]);

    // Orden 2: Proveedor A, dentro de fecha
    PaymentOrder::factory()->create([
        'supplier_id' => $supplierA->id,
        'date' => '2026-09-12',
        'total_amount' => '7654.50',
        'status' => PaymentOrderStatus::Issued,
    ]);

    // Orden 3: Proveedor A, fuera de fecha
    PaymentOrder::factory()->create([
        'supplier_id' => $supplierA->id,
        'date' => '2026-08-01',
        'total_amount' => '99999.00',
        'status' => PaymentOrderStatus::Issued,
    ]);

    // Orden 4: Proveedor B, dentro de fecha
    PaymentOrder::factory()->create([
        'supplier_id' => $supplierB->id,
        'date' => '2026-09-10',
        'total_amount' => '50000.00',
        'status' => PaymentOrderStatus::Issued,
    ]);

    // Filtramos por Proveedor A y rango 2026-09-01 al 2026-09-30
    // Total esperado: 12345.50 + 7654.50 = 20000.00
    $this->actingAs($user)
        ->get(route('purchasing.payment-orders.index', [
            'supplier_id' => $supplierA->id,
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 2)
            ->where('totalEgresses', '20000.00')
            ->where('filters.supplier_id', (string) $supplierA->id)
            ->where('filters.date_from', '2026-09-01')
            ->where('filters.date_to', '2026-09-30')
        );
});

test('filters orders by payment method', function () {
    $user = User::factory()->create();
    $methodCheque = PaymentMethod::factory()->create(['name' => 'Cheque']);
    $methodEfectivo = PaymentMethod::factory()->create(['name' => 'Efectivo']);

    $orderCheque = PaymentOrder::factory()->create();
    PaymentOrderMethod::factory()->create([
        'payment_order_id' => $orderCheque->id,
        'payment_method_id' => $methodCheque->id,
    ]);

    $orderEfectivo = PaymentOrder::factory()->create();
    PaymentOrderMethod::factory()->create([
        'payment_order_id' => $orderEfectivo->id,
        'payment_method_id' => $methodEfectivo->id,
    ]);

    $this->actingAs($user)
        ->get(route('purchasing.payment-orders.index', [
            'payment_method_id' => $methodCheque->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $orderCheque->id)
        );
});

test('filters orders by voucher type', function () {
    $user = User::factory()->create();

    $orderWithCreditNote = PaymentOrder::factory()->create();
    $creditNote = SupplierVoucher::factory()->creditNote()->create();
    PaymentOrderItem::factory()->create([
        'payment_order_id' => $orderWithCreditNote->id,
        'supplier_voucher_id' => $creditNote->id,
    ]);

    $orderWithInvoice = PaymentOrder::factory()->create();
    $invoice = SupplierVoucher::factory()->invoice()->create();
    PaymentOrderItem::factory()->create([
        'payment_order_id' => $orderWithInvoice->id,
        'supplier_voucher_id' => $invoice->id,
    ]);

    $this->actingAs($user)
        ->get(route('purchasing.payment-orders.index', [
            'voucher_type' => SupplierVoucherType::CreditNote->value,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $orderWithCreditNote->id)
        );
});

test('filters orders by status', function () {
    $user = User::factory()->create();

    PaymentOrder::factory()->create([
        'status' => PaymentOrderStatus::Issued,
    ]);

    $cancelled = PaymentOrder::factory()->cancelled()->create();

    $this->actingAs($user)
        ->get(route('purchasing.payment-orders.index', [
            'status' => PaymentOrderStatus::Cancelled->value,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $cancelled->id)
            ->where('totalEgresses', '0.00') // Las anuladas no suman egreso
        );
});

test('exports payment orders and vouchers to CSV in streaming', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['business_name' => 'Lácteos Salteños']);
    $order = PaymentOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'order_number' => 'OP-00009999',
        'total_amount' => '5432.10',
        'date' => '2026-09-17',
    ]);

    $voucher = SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'number' => '00012345',
    ]);

    PaymentOrderItem::factory()->create([
        'payment_order_id' => $order->id,
        'supplier_voucher_id' => $voucher->id,
        'amount_applied' => '5432.10',
    ]);

    $response = $this->actingAs($user)
        ->get(route('purchasing.payment-orders.export.csv'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $content = $response->streamedContent();
    expect($content)
        ->toContain('N° Orden')
        ->toContain('OP-00009999')
        ->toContain('Lácteos Salteños')
        ->toContain('5432.10');
});

test('exports payment orders and vouchers to Excel XLSX', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['business_name' => 'Bebidas del Norte']);
    $order = PaymentOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'order_number' => 'OP-00008888',
        'total_amount' => '12000.00',
        'date' => '2026-09-17',
    ]);

    $response = $this->actingAs($user)
        ->get(route('purchasing.payment-orders.export.excel'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});
