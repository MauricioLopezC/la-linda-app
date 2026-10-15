<?php

use App\Actions\Purchasing\AnnulPaymentOrder;
use App\Actions\Purchasing\IssuePaymentOrder;
use App\Actions\Reporting\GetDashboardExpenses;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\Purchasing\PaymentOrder;
use App\Models\Purchasing\PaymentOrderItem;
use App\Models\Purchasing\PaymentOrderMethod;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\VoucherApplication;
use App\Models\Sales\CashMovement;
use App\Models\Sales\CashSession;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 12:00:00');
    $this->filters = ['date_from' => '2026-10-01', 'date_to' => '2026-10-15', 'branch_id' => null, 'channel' => null];
    $this->expenses = app(GetDashboardExpenses::class);
});

test('partial payment and annulment change disbursements without changing accrual', function () {
    $user = User::factory()->gerente()->create();
    $invoice = SupplierVoucher::factory()->invoice()->create([
        'issue_date' => '2026-10-01', 'due_date' => null, 'total_amount' => '100000.00',
    ]);
    $method = PaymentMethod::factory()->cash()->create();
    $result = app(IssuePaymentOrder::class)->handle([
        'supplier_id' => $invoice->supplier_id, 'date' => '2026-10-15', 'notes' => null,
        'payment_methods' => [['payment_method_id' => $method->id, 'amount' => '40000.00']],
        'items' => [['supplier_voucher_id' => $invoice->id, 'amount_applied' => '40000.00']],
    ], $user->id);

    $this->actingAs($user)->get(route('dashboard', $this->filters))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('expenses.accrued_total', '100000.00')
        ->where('expenses.paid_to_suppliers_total', '40000.00')
        ->where('expenses.cash_expenses_total', '0.00')
        ->where('expenses.disbursed_total', '40000.00')
        ->where('expenses.by_payment_method.0.amount', '40000.00'));

    app(AnnulPaymentOrder::class)->handle(PaymentOrder::findOrFail($result->id), $user->id);
    $this->get(route('dashboard', $this->filters))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('expenses.accrued_total', '100000.00')
            ->where('expenses.paid_to_suppliers_total', '0.00')
            ->where('expenses.disbursed_total', '0.00')
            ->where('expenses.by_payment_method', []));
});

test('accrual includes paid invoices and debit notes and subtracts applied credit notes once', function () {
    $invoice = SupplierVoucher::factory()->invoice()->create([
        'issue_date' => '2026-10-03', 'due_date' => null, 'total_amount' => '100.10', 'status' => SupplierVoucherStatus::Paid,
    ]);
    SupplierVoucher::factory()->debitNote()->create(['issue_date' => '2026-10-03', 'due_date' => null, 'total_amount' => '20.20']);
    $credit = SupplierVoucher::factory()->creditNote()->create([
        'supplier_id' => $invoice->supplier_id, 'issue_date' => '2026-10-03',
        'total_amount' => '30.30', 'status' => SupplierVoucherStatus::Applied,
    ]);
    VoucherApplication::factory()->from($credit)->to($invoice)->amount('30.30')->create();
    SupplierVoucher::factory()->invoice()->create([
        'issue_date' => '2026-10-03', 'due_date' => null, 'total_amount' => '900.00', 'status' => SupplierVoucherStatus::Cancelled,
    ]);
    SupplierVoucher::factory()->creditNote()->create([
        'issue_date' => '2026-10-03', 'total_amount' => '500.00', 'status' => SupplierVoucherStatus::Cancelled,
    ]);
    SupplierVoucher::factory()->remito()->create(['issue_date' => '2026-10-03', 'total_amount' => '50.00']);
    $data = $this->expenses->handle($this->filters);
    expect($data->accrued_total)->toBe('90.00')->and($data->paid_to_suppliers_total)->toBe('0.00');
});

test('document dates include both boundaries independently of creation dates', function () {
    foreach (['2026-09-30', '2026-10-01', '2026-10-15', '2026-10-16'] as $date) {
        SupplierVoucher::factory()->invoice()->create([
            'issue_date' => $date, 'due_date' => null, 'total_amount' => '10.01', 'created_at' => '2026-09-01',
        ]);
        $order = PaymentOrder::factory()->create(['date' => $date, 'total_amount' => '3.01', 'created_at' => '2026-09-01']);
        PaymentOrderMethod::factory()->create(['payment_order_id' => $order->id, 'amount' => '3.01']);
    }
    $data = $this->expenses->handle($this->filters);
    expect($data->accrued_total)->toBe('20.02')->and($data->paid_to_suppliers_total)->toBe('6.02');
});

test('payments for previous invoices count and future payments of current invoices do not', function () {
    $old = SupplierVoucher::factory()->invoice()->create(['issue_date' => '2026-09-01', 'due_date' => null, 'total_amount' => '100.00']);
    $current = SupplierVoucher::factory()->invoice()->create(['issue_date' => '2026-10-01', 'due_date' => null, 'total_amount' => '200.00']);
    foreach ([[$old, '2026-10-10', '40.00'], [$current, '2026-11-01', '80.00']] as [$invoice, $date, $amount]) {
        $order = PaymentOrder::factory()->create(['supplier_id' => $invoice->supplier_id, 'date' => $date, 'total_amount' => $amount]);
        PaymentOrderItem::factory()->create(['payment_order_id' => $order->id, 'supplier_voucher_id' => $invoice->id, 'amount_applied' => $amount]);
        PaymentOrderMethod::factory()->create(['payment_order_id' => $order->id, 'amount' => $amount]);
    }
    $data = $this->expenses->handle($this->filters);
    expect($data->accrued_total)->toBe('200.00')->and($data->paid_to_suppliers_total)->toBe('40.00');
});

test('breakdown counts distinct orders without multiplying voucher items and includes inactive methods', function () {
    $cash = PaymentMethod::factory()->cash()->create(['is_active' => false]);
    $transfer = PaymentMethod::factory()->create();
    $order = PaymentOrder::factory()->create(['date' => '2026-10-05', 'total_amount' => '30.03']);
    foreach (['10.01', '20.02'] as $amount) {
        $invoice = SupplierVoucher::factory()->invoice()->create(['issue_date' => '2026-10-01', 'due_date' => null, 'total_amount' => $amount]);
        PaymentOrderItem::factory()->create(['payment_order_id' => $order->id, 'supplier_voucher_id' => $invoice->id, 'amount_applied' => $amount]);
    }
    PaymentOrderMethod::factory()->create(['payment_order_id' => $order->id, 'payment_method_id' => $cash->id, 'amount' => '10.01']);
    PaymentOrderMethod::factory()->create(['payment_order_id' => $order->id, 'payment_method_id' => $transfer->id, 'amount' => '20.02']);
    $second = PaymentOrder::factory()->create(['date' => '2026-10-05', 'total_amount' => '0.01']);
    PaymentOrderMethod::factory()->create(['payment_order_id' => $second->id, 'payment_method_id' => $cash->id, 'amount' => '0.01']);
    $cancelled = PaymentOrder::factory()->cancelled()->create(['date' => '2026-10-05', 'total_amount' => '999.99']);
    PaymentOrderMethod::factory()->create(['payment_order_id' => $cancelled->id, 'payment_method_id' => $cash->id, 'amount' => '999.99']);
    $data = $this->expenses->handle($this->filters)->toArray();
    expect($data['paid_to_suppliers_total'])->toBe('30.04')
        ->and($data['by_payment_method'])->toBe([
            ['key' => (string) $cash->id, 'label' => $cash->name, 'amount' => '10.02', 'count' => 2],
            ['key' => (string) $transfer->id, 'label' => $transfer->name, 'amount' => '20.02', 'count' => 1],
        ]);
});

test('cash includes full boundary days and excludes other movement types', function () {
    foreach (['2026-09-30 23:59:59', '2026-10-01 00:00:00', '2026-10-15 23:59:59', '2026-10-16 00:00:00'] as $date) {
        CashMovement::factory()->expense()->create(['created_at' => $date, 'amount' => '0.01']);
    }
    CashMovement::factory()->create(['amount' => '100.00']);
    CashMovement::factory()->opening()->create(['amount' => '100.00']);
    CashMovement::factory()->forSale(Sale::factory()->create())->create(['amount' => '100.00']);
    $data = $this->expenses->handle($this->filters);
    expect($data->cash_expenses_total)->toBe('0.02')->and($data->disbursed_total)->toBe('0.02');
});

test('only cash is branch filtered and channel never changes expenses', function () {
    $branch = Branch::factory()->create();
    $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id]);
    $session = CashSession::factory()->create(['point_of_sale_id' => PointOfSale::factory()->create(['warehouse_id' => $warehouse->id])->id]);
    CashMovement::factory()->expense()->create(['cash_session_id' => $session->id, 'amount' => '2.50']);
    CashMovement::factory()->expense()->create(['amount' => '5.00']);
    SupplierVoucher::factory()->invoice()->create(['issue_date' => '2026-10-01', 'due_date' => null, 'total_amount' => '100.00']);
    $order = PaymentOrder::factory()->create(['date' => '2026-10-01', 'total_amount' => '40.00']);
    PaymentOrderMethod::factory()->create(['payment_order_id' => $order->id, 'amount' => '40.00']);
    expect($this->expenses->handle($this->filters)->disbursed_total)->toBe('47.50');
    foreach ([null, 'online', 'mostrador'] as $channel) {
        $data = $this->expenses->handle([...$this->filters, 'branch_id' => $branch->id, 'channel' => $channel]);
        expect($data->accrued_total)->toBe('100.00')->and($data->paid_to_suppliers_total)->toBe('40.00')
            ->and($data->cash_expenses_total)->toBe('2.50')->and($data->disbursed_total)->toBe('42.50');
    }
});

test('empty periods serialize zeros and credits can make accrual negative', function () {
    expect($this->expenses->handle($this->filters)->toArray())->toBe([
        'accrued_total' => '0.00', 'paid_to_suppliers_total' => '0.00',
        'cash_expenses_total' => '0.00', 'disbursed_total' => '0.00', 'by_payment_method' => [],
    ]);
    SupplierVoucher::factory()->creditNote()->create(['issue_date' => '2026-10-10', 'total_amount' => '0.01']);
    expect($this->expenses->handle($this->filters)->accrued_total)->toBe('-0.01');
});

test('dashboard access is restricted to managers and administrators', function (string $role, bool $allowed) {
    $response = $this->actingAs(User::factory()->state(['role' => $role])->create())->get(route('dashboard'));
    if ($allowed) {
        $response->assertOk();
    } else {
        $response->assertForbidden();
    }
})->with([
    ['gerente', true], ['administrador', true], ['compras', false], ['deposito', false], ['cajero', false],
]);

test('customers cannot access dashboard expenses', function () {
    $this->actingAs(User::factory()->state(['role' => 'cliente'])->create())->get(route('dashboard'))->assertRedirect(route('tienda.home'));
});
