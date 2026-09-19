<?php

use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Models\Purchasing\PaymentOrderItem;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\VoucherApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('without a supplier the page renders an empty statement', function () {
    Supplier::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('purchasing.account-statement.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/account-statement/index')
            ->where('totals', null)
            ->where('items', [])
        );
});

test('totals and pending detail are derived from vouchers, payments and credit notes', function () {
    $supplier = Supplier::factory()->create();

    $fullyPaidInvoice = SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '1000.00',
    ]);
    PaymentOrderItem::factory()->forInvoice($fullyPaidInvoice, '1000.00')->create();

    $partiallyPaidInvoice = SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '800.00',
    ]);
    PaymentOrderItem::factory()->forInvoice($partiallyPaidInvoice, '300.00')->create();
    VoucherApplication::factory()
        ->from(SupplierVoucher::factory()->creditNote()->create([
            'supplier_id' => $supplier->id,
            'total_amount' => '200.00',
        ]))
        ->to($partiallyPaidInvoice)
        ->amount('200.00')
        ->create();

    $unpaidDebitNote = SupplierVoucher::factory()->debitNote()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '400.00',
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('purchasing.account-statement.index', ['supplier_id' => $supplier->id]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('purchasing/account-statement/index')
        ->where('totals.total_received', '2200.00')
        ->where('totals.total_paid', '1500.00')
        ->where('totals.balance', '700.00')
        ->has('items', 2)
    );

    $items = $response->viewData('page')['props']['items'];
    $ids = collect($items)->pluck('id')->all();

    expect($ids)->toContain($partiallyPaidInvoice->id, $unpaidDebitNote->id)
        ->and($ids)->not->toContain($fullyPaidInvoice->id);
});

test('the date range filters which vouchers count toward totals and detail', function () {
    $supplier = Supplier::factory()->create();

    $inRange = SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '500.00',
        'issue_date' => now()->subDays(5),
        'due_date' => null,
    ]);
    $outOfRange = SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '900.00',
        'issue_date' => now()->subDays(40),
        'due_date' => null,
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('purchasing.account-statement.index', [
            'supplier_id' => $supplier->id,
            'date_from' => now()->subDays(10)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('totals.total_received', '500.00')
        ->where('totals.balance', '500.00')
        ->has('items', 1)
    );

    $items = $response->viewData('page')['props']['items'];
    expect(collect($items)->pluck('id')->all())
        ->toContain($inRange->id)
        ->not->toContain($outOfRange->id);
});

test('a cancelled voucher does not participate in totals or the pending detail', function () {
    $supplier = Supplier::factory()->create();

    SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '5000.00',
        'status' => SupplierVoucherStatus::Cancelled,
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('purchasing.account-statement.index', ['supplier_id' => $supplier->id]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('totals.total_received', '0.00')
        ->where('totals.balance', '0.00')
        ->has('items', 0)
    );
});

test('a voucher past its due date is flagged as overdue in the detail', function () {
    $supplier = Supplier::factory()->create();

    $overdue = SupplierVoucher::factory()->overdue()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '300.00',
    ]);
    $current = SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '150.00',
        'due_date' => now()->addDays(10),
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('purchasing.account-statement.index', ['supplier_id' => $supplier->id]));

    $items = collect($response->viewData('page')['props']['items'])->keyBy('id');

    expect($items[$overdue->id]['is_overdue'])->toBeTrue()
        ->and($items[$overdue->id]['aging_days'])->toBe(45)
        ->and($items[$current->id]['is_overdue'])->toBeFalse();
});

test('exporting without a supplier is rejected', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('purchasing.account-statement.export.csv'))
        ->assertStatus(422);

    $this->actingAs(User::factory()->create())
        ->get(route('purchasing.account-statement.export.excel'))
        ->assertStatus(422);
});

test('csv export downloads the same figures shown on screen', function () {
    $supplier = Supplier::factory()->create();
    SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '1234.00',
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('purchasing.account-statement.export.csv', ['supplier_id' => $supplier->id]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $content = $response->streamedContent();
    expect($content)
        ->toContain($supplier->business_name)
        ->toContain('1234.00');
});

test('xlsx export downloads successfully', function () {
    $supplier = Supplier::factory()->create();
    SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '999.00',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('purchasing.account-statement.export.excel', ['supplier_id' => $supplier->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('the account statement requires authentication', function () {
    $this->get(route('purchasing.account-statement.index'))->assertRedirect(route('login'));
    $this->get(route('purchasing.account-statement.export.csv'))->assertRedirect(route('login'));
    $this->get(route('purchasing.account-statement.export.excel'))->assertRedirect(route('login'));
});
