<?php

use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\VoucherApplication;
use App\Models\User;
use App\Rules\Purchasing\ValidCuit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, mixed> */
function validSupplierVoucherData(Supplier $supplier, array $overrides = []): array
{
    return array_merge([
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Invoice->value,
        'letter' => SupplierVoucherLetter::A->value,
        'point_of_sale' => '12',
        'number' => '345',
        'issue_date' => today()->toDateString(),
        'due_date' => today()->addDays(30)->toDateString(),
        'net_amount' => '1000,00',
        'other_taxes_amount' => '15,50',
        'notes' => '  Compra mensual  ',
    ], $overrides);
}

test('guest cannot access supplier voucher pages', function () {
    $voucher = SupplierVoucher::factory()->create();

    $this->get(route('purchasing.vouchers.index'))->assertRedirect(route('login'));
    $this->get(route('purchasing.vouchers.create'))->assertRedirect(route('login'));
    $this->post(route('purchasing.vouchers.store'))->assertRedirect(route('login'));
    $this->get(route('purchasing.vouchers.pdf', $voucher))->assertRedirect(route('login'));
});

test('creation page only offers active suppliers and closed fiscal options', function () {
    $user = User::factory()->create();
    $activeSupplier = Supplier::factory()->create(['business_name' => 'Proveedor Activo']);
    Supplier::factory()->inactive()->create(['business_name' => 'Proveedor Inactivo']);

    $this->actingAs($user)
        ->get(route('purchasing.vouchers.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/vouchers/create')
            ->has('suppliers', 1)
            ->where('suppliers.0.id', $activeSupplier->id)
            ->where('suppliers.0.business_name', 'Proveedor Activo')
            ->has('voucherTypes', 3)
            ->has('letters', 4)
            ->where('today', today()->toDateString()));
});

test('user can register an invoice with normalized fiscal numbers and automatic amounts', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();

    $this->actingAs($user)
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('purchasing.vouchers.index'));

    $voucher = SupplierVoucher::query()->sole();

    expect($voucher)
        ->supplier_id->toBe($supplier->id)
        ->type->toBe(SupplierVoucherType::Invoice)
        ->letter->toBe(SupplierVoucherLetter::A)
        ->point_of_sale->toBe('0012')
        ->number->toBe('00000345')
        ->net_amount->toBe('1000.00')
        ->vat_amount->toBe('210.00')
        ->other_taxes_amount->toBe('15.50')
        ->total_amount->toBe('1225.50')
        ->status->toBe(SupplierVoucherStatus::Pending)
        ->pendingBalance()->toBe('1225.50')
        ->notes->toBe('Compra mensual');
});

test('credit and debit notes are born pending application', function (SupplierVoucherType $type) {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();

    $this->actingAs($user)
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, [
            'type' => $type->value,
            'due_date' => null,
        ]))
        ->assertSessionHasNoErrors();

    expect(SupplierVoucher::query()->sole()->status)
        ->toBe(SupplierVoucherStatus::PendingApplication);
})->with([
    'credit note' => SupplierVoucherType::CreditNote,
    'debit note' => SupplierVoucherType::DebitNote,
]);

test('required voucher fields are validated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('purchasing.vouchers.store'), [])
        ->assertSessionHasErrors([
            'supplier_id', 'type', 'letter', 'point_of_sale', 'number',
            'issue_date',
        ]);
});

test('voucher rejects inactive or missing suppliers', function () {
    $user = User::factory()->create();
    $inactiveSupplier = Supplier::factory()->inactive()->create();

    $this->actingAs($user)
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($inactiveSupplier))
        ->assertSessionHasErrors(['supplier_id']);

    $this->actingAs($user)
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($inactiveSupplier, ['supplier_id' => 999999]))
        ->assertSessionHasErrors(['supplier_id']);
});

test('voucher validates dates and fiscal number format', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();

    $this->actingAs($user)
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, [
            'point_of_sale' => '12345',
            'number' => '12A',
            'issue_date' => today()->addDay()->toDateString(),
            'due_date' => today()->subDay()->toDateString(),
        ]))
        ->assertSessionHasErrors(['point_of_sale', 'number', 'issue_date', 'due_date']);
});

test('voucher validates monetary values', function (array $amounts, array $errors) {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();

    $this->actingAs($user)
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, $amounts))
        ->assertSessionHasErrors($errors);
})->with([
    'zero calculated total' => [['net_amount' => '0', 'other_taxes_amount' => '0'], ['net_amount']],
    'negative component' => [['net_amount' => '-1', 'other_taxes_amount' => '0'], ['net_amount']],
    'too many decimals' => [['net_amount' => '1000.001'], ['net_amount']],
    'above decimal limit' => [['net_amount' => '10000000000', 'other_taxes_amount' => '0'], ['net_amount']],
]);

test('derived amounts status and pending balance cannot be supplied by the client', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();

    $this->actingAs($user)
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, [
            'vat_amount' => '1.00',
            'total_amount' => '1.00',
            'status' => SupplierVoucherStatus::Paid->value,
            'pending_balance' => '0.00',
        ]))
        ->assertSessionHasErrors(['vat_amount', 'total_amount', 'status', 'pending_balance']);
});

test('letters A and M calculate VAT automatically at a fixed 21 percent with commercial rounding', function (
    SupplierVoucherLetter $letter,
    string $netAmount,
    string $expectedVat,
    string $expectedTotal,
) {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();

    $this->actingAs($user)
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, [
            'letter' => $letter->value,
            'net_amount' => $netAmount,
            'other_taxes_amount' => '0.00',
        ]))
        ->assertSessionHasNoErrors();

    $voucher = SupplierVoucher::query()->sole();

    expect($voucher->vat_amount)->toBe($expectedVat)
        ->and($voucher->total_amount)->toBe($expectedTotal);
})->with([
    'A standard amount' => [SupplierVoucherLetter::A, '100.00', '21.00', '121.00'],
    'M rounds half cent upward' => [SupplierVoucherLetter::M, '0.03', '0.01', '0.04'],
]);

test('letters B and C do not discriminate VAT', function (SupplierVoucherLetter $letter) {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();

    $this->actingAs($user)
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, [
            'letter' => $letter->value,
            'net_amount' => '100.00',
            'other_taxes_amount' => '5.00',
        ]))
        ->assertSessionHasNoErrors();

    $voucher = SupplierVoucher::query()->sole();

    expect($voucher->vat_amount)->toBe('0.00')
        ->and($voucher->total_amount)->toBe('105.00');
})->with([
    'B does not discriminate VAT' => SupplierVoucherLetter::B,
    'C has no VAT' => SupplierVoucherLetter::C,
]);

test('voucher fiscal identity is unique and each component participates in it', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();

    $this->actingAs($user)->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier))
        ->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier))
        ->assertSessionHasErrors(['number']);

    foreach ([
        ['supplier_id' => $otherSupplier->id],
        ['type' => SupplierVoucherType::CreditNote->value],
        ['letter' => SupplierVoucherLetter::B->value],
        ['point_of_sale' => '13'],
        ['number' => '346'],
    ] as $variation) {
        $this->actingAs($user)
            ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, $variation))
            ->assertSessionHasNoErrors();
    }

    expect(SupplierVoucher::query()->count())->toBe(6);
});

test('listing exposes fiscal data derived balance state and overdue marker', function () {
    Carbon::setTestNow('2026-08-31 12:00:00');
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['business_name' => 'Lácteos del Sur']);
    $voucher = SupplierVoucher::factory()->overdue()->create([
        'supplier_id' => $supplier->id,
        'point_of_sale' => '0007',
        'number' => '00001234',
        'net_amount' => '100.00',
        'vat_amount' => '21.00',
        'other_taxes_amount' => '0.00',
        'total_amount' => '121.00',
    ]);

    $this->actingAs($user)
        ->get(route('purchasing.vouchers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/vouchers/index')
            ->has('vouchers.data', 1)
            ->where('vouchers.data.0.id', $voucher->id)
            ->where('vouchers.data.0.supplier_business_name', 'Lácteos del Sur')
            ->where('vouchers.data.0.formatted_number', 'A 0007-00001234')
            ->where('vouchers.data.0.total_amount', '121.00')
            ->where('vouchers.data.0.outstanding_amount', '121.00')
            ->where('vouchers.data.0.status', SupplierVoucherStatus::Pending->value)
            ->where('vouchers.data.0.is_overdue', true));

    Carbon::setTestNow();
});

test('listing exposes a note outstanding amount as what is still unapplied, not its full total', function () {
    // Regression: outstanding_amount must dispatch by type (HU-054). A note is never a payment or
    // application target, so reading pendingBalance() unconditionally would always report its
    // full total even after part of it has been imputed to an invoice.
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $creditNote = SupplierVoucher::factory()->creditNote()->create([
        'supplier_id' => $supplier->id,
        'issue_date' => today(),
        'net_amount' => '500.00',
        'vat_amount' => '0.00',
        'other_taxes_amount' => '0.00',
        'total_amount' => '500.00',
    ]);
    $invoice = SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'issue_date' => today(),
        'net_amount' => '1000.00',
        'vat_amount' => '0.00',
        'other_taxes_amount' => '0.00',
        'total_amount' => '1000.00',
    ]);
    VoucherApplication::factory()->from($creditNote)->to($invoice)->amount('150.00')->create();

    $this->actingAs($user)
        ->get(route('purchasing.vouchers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/vouchers/index')
            ->where('vouchers.data.1.id', $creditNote->id)
            ->where('vouchers.data.1.outstanding_amount', '350.00'));
});

test('voucher has no edit update or delete route in this story', function () {
    expect(Route::has('purchasing.vouchers.edit'))->toBeFalse()
        ->and(Route::has('purchasing.vouchers.update'))->toBeFalse()
        ->and(Route::has('purchasing.vouchers.destroy'))->toBeFalse();
});

test('authenticated user can download the internal voucher PDF', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['business_name' => 'Proveedor PDF']);
    $voucher = SupplierVoucher::factory()->create([
        'supplier_id' => $supplier->id,
        'point_of_sale' => '0004',
        'number' => '00001234',
    ]);

    $response = $this->actingAs($user)->get(route('purchasing.vouchers.pdf', $voucher));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('comprobante-A-0004-00001234.pdf');

    expect($response->getContent())->toStartWith('%PDF');
});

test('voucher PDF uses the selected type and letter with the educational company identity', function (
    SupplierVoucherType $type,
    SupplierVoucherLetter $letter,
    string $expectedTitle,
) {
    $supplier = Supplier::factory()->create();
    $voucher = SupplierVoucher::factory()->make([
        'supplier_id' => $supplier->id,
        'type' => $type,
        'letter' => $letter,
    ]);
    $voucher->id = 36;
    $voucher->setRelation('supplier', $supplier);

    $html = view('pdf.purchasing.supplier-voucher', [
        'voucher' => $voucher,
        'supplierTaxId' => ValidCuit::format($supplier->tax_id),
        'company' => config('company'),
        'stylesheet' => '',
    ])->render();

    expect($html)
        ->toContain($expectedTitle)
        ->toContain('Supermercados La Linda S.A.')
        ->toContain('30-71654321-4')
        ->toContain('Salta Capital, Salta')
        ->toContain('No reemplaza ni modifica el comprobante fiscal original')
        ->and(ValidCuit::isValidChecksum(ValidCuit::sanitize((string) config('company.tax_id'))))->toBeTrue();
})->with([
    'factura A' => [SupplierVoucherType::Invoice, SupplierVoucherLetter::A, 'Factura A'],
    'nota de crédito B' => [SupplierVoucherType::CreditNote, SupplierVoucherLetter::B, 'Nota de crédito B'],
    'nota de débito C' => [SupplierVoucherType::DebitNote, SupplierVoucherLetter::C, 'Nota de débito C'],
    'factura M' => [SupplierVoucherType::Invoice, SupplierVoucherLetter::M, 'Factura M'],
]);

test('database protects fiscal uniqueness and amount consistency', function () {
    $supplier = Supplier::factory()->create();
    SupplierVoucher::factory()->create([
        'supplier_id' => $supplier->id,
        'point_of_sale' => '0001',
        'number' => '00000001',
    ]);

    expect(fn () => SupplierVoucher::factory()->create([
        'supplier_id' => $supplier->id,
        'point_of_sale' => '0001',
        'number' => '00000001',
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('supplier_vouchers')->insert([
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Invoice->value,
        'letter' => SupplierVoucherLetter::A->value,
        'point_of_sale' => '0002',
        'number' => '00000002',
        'issue_date' => today()->toDateString(),
        'net_amount' => '100.00',
        'vat_amount' => '21.00',
        'other_taxes_amount' => '0.00',
        'total_amount' => '120.00',
        'status' => SupplierVoucherStatus::Pending->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('supplier with vouchers cannot be physically deleted', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    SupplierVoucher::factory()->create(['supplier_id' => $supplier->id]);

    $this->actingAs($user)
        ->delete(route('purchasing.suppliers.destroy', $supplier))
        ->assertSessionHasErrors(['supplier']);

    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
});
