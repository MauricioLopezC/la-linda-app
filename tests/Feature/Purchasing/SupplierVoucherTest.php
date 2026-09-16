<?php

use App\Actions\Purchasing\AssociateCreditNoteToInvoice;
use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Catalog\Article;
use App\Models\Purchasing\PaymentOrderItem;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\SupplierVoucherItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, mixed> */
function validSupplierVoucherData(Supplier $supplier, ?Article $article = null, array $overrides = []): array
{
    return array_replace_recursive([
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Invoice->value,
        'letter' => SupplierVoucherLetter::A->value,
        'point_of_sale' => '12',
        'number' => '345',
        'issue_date' => today()->toDateString(),
        'due_date' => today()->addDays(30)->toDateString(),
        'total_amount' => '1.300,50',
        'notes' => '  Compra mensual  ',
        'items' => [
            [
                'article_id' => $article?->id,
                'description' => '  Harina 000 original  ',
                'quantity' => '1,05',
                'unit_of_measure' => '  kg  ',
                'unit_price' => '1.000,00',
                'line_total' => '1.050,00',
            ],
            [
                'article_id' => null,
                'description' => 'Cargo financiero',
                'line_total' => '200,00',
            ],
        ],
    ], $overrides);
}

test('guest cannot access supplier voucher pages', function () {
    $voucher = SupplierVoucher::factory()->create();

    $this->get(route('purchasing.vouchers.index'))->assertRedirect(route('login'));
    $this->get(route('purchasing.vouchers.create'))->assertRedirect(route('login'));
    $this->get(route('purchasing.vouchers.articles', ['search' => 'harina']))->assertRedirect(route('login'));
    $this->get(route('purchasing.vouchers.associable-invoices', ['supplier_id' => $voucher->supplier_id]))->assertRedirect(route('login'));
    $this->post(route('purchasing.vouchers.store'))->assertRedirect(route('login'));
    $this->get(route('purchasing.vouchers.show', $voucher))->assertRedirect(route('login'));
    $this->post(route('purchasing.vouchers.annul', $voucher))->assertRedirect(route('login'));
});

test('creation page only loads active suppliers without preloading the article catalog', function () {
    $user = User::factory()->create();
    $activeSupplier = Supplier::factory()->create(['business_name' => 'Proveedor Activo']);
    Supplier::factory()->inactive()->create();
    $this->actingAs($user)->get(route('purchasing.vouchers.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/vouchers/create')
            ->has('suppliers', 1)
            ->where('suppliers.0.id', $activeSupplier->id)
            ->missing('articles')
            ->has('voucherTypes', 3)
            ->has('letters', 4));
});

test('article search returns only matching active articles and limits the result set', function () {
    $user = User::factory()->create();
    $matchingArticle = Article::factory()->create([
        'internal_code' => 'ART-BUSCADOR',
        'description' => 'Harina especial para buscador',
        'barcode' => '7791234567000',
    ]);
    Article::factory()->inactive()->create([
        'description' => 'Harina especial para buscador inactiva',
    ]);
    Article::factory()->count(25)->create([
        'description' => 'Producto masivo buscador',
    ]);

    $this->actingAs($user)
        ->getJson(route('purchasing.vouchers.articles', ['search' => 'ART-BUSCADOR']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $matchingArticle->id)
        ->assertJsonPath('0.internal_code', 'ART-BUSCADOR');

    $this->actingAs($user)
        ->getJson(route('purchasing.vouchers.articles', ['search' => '7791234567000']))
        ->assertOk()
        ->assertJsonPath('0.id', $matchingArticle->id);

    $this->actingAs($user)
        ->getJson(route('purchasing.vouchers.articles', ['search' => 'masivo buscador']))
        ->assertOk()
        ->assertJsonCount(20);
});

test('article search requires at least two characters', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('purchasing.vouchers.articles', ['search' => 'a']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['search']);
});

test('user registers the transcribed total and complete historical lines', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $article = Article::factory()->create(['description' => 'Descripción actual']);

    $response = $this->actingAs($user)
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, $article))
        ->assertSessionHasNoErrors();

    $voucher = SupplierVoucher::query()->sole();
    $response->assertRedirect(route('purchasing.vouchers.show', $voucher));

    expect($voucher)
        ->supplier_id->toBe($supplier->id)
        ->point_of_sale->toBe('0012')
        ->number->toBe('00000345')
        ->total_amount->toBe('1300.50')
        ->status->toBe(SupplierVoucherStatus::Pending)
        ->notes->toBe('Compra mensual')
        ->itemsTotal()->toBe('1250.00')
        ->differenceAmount()->toBe('50.50');

    expect($voucher->items)->toHaveCount(2)
        ->and($voucher->items[0]->article_id)->toBe($article->id)
        ->and($voucher->items[0]->description)->toBe('Harina 000 original')
        ->and($voucher->items[0]->unit_of_measure)->toBe('kg')
        ->and($voucher->items[0]->quantity)->toBe('1.050')
        ->and($voucher->items[0]->unit_price)->toBe('1000.00')
        ->and($voucher->items[0]->line_total)->toBe('1050.00')
        // Concept line: quantity, unit and unit price are derived, not transcribed.
        ->and($voucher->items[1]->article_id)->toBeNull()
        ->and($voucher->items[1]->description)->toBe('Cargo financiero')
        ->and($voucher->items[1]->quantity)->toBe('1.000')
        ->and($voucher->items[1]->unit_of_measure)->toBe('—')
        ->and($voucher->items[1]->unit_price)->toBe('200.00')
        ->and($voucher->items[1]->line_total)->toBe('200.00');
});

test('article line quantity accepts no more than two decimal places', function () {
    $supplier = Supplier::factory()->create();
    $article = Article::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, null, [
            'items' => [[
                'article_id' => $article->id,
                'description' => 'Artículo fraccionado',
                'quantity' => '1,234',
                'unit_of_measure' => 'kg',
                'unit_price' => '1.000,00',
                'line_total' => '1.234,00',
            ]],
        ]))
        ->assertSessionHasErrors(['items.0.quantity']);
});

test('a concept line only needs a description and an amount', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();

    $this->actingAs($user)
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, null, [
            'items' => [[
                'article_id' => null,
                'description' => 'Bonificación 10% s/factura',
                'line_total' => '5.000,00',
            ]],
        ]))
        ->assertSessionHasNoErrors();

    $concept = SupplierVoucher::query()->sole()
        ->items->firstWhere('description', 'Bonificación 10% s/factura');

    expect($concept)->not->toBeNull()
        ->and($concept->article_id)->toBeNull()
        ->and($concept->quantity)->toBe('1.000')
        ->and($concept->unit_of_measure)->toBe('—')
        ->and($concept->unit_price)->toBe('5000.00')
        ->and($concept->line_total)->toBe('5000.00');
});

test('a concept line still requires its amount but never its quantity or unit', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, null, [
            'items' => [[
                'article_id' => null,
                'description' => 'Ajuste sin importe',
                'line_total' => '',
            ]],
        ]))
        ->assertSessionHasErrors(['items.0.line_total'])
        ->assertSessionDoesntHaveErrors(['items.0.quantity', 'items.0.unit_of_measure']);
});

test('voucher types are born with their derived initial state', function (
    SupplierVoucherType $type,
    SupplierVoucherStatus $expectedStatus,
) {
    $supplier = Supplier::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, null, [
            'type' => $type->value,
            'due_date' => null,
        ]))
        ->assertSessionHasNoErrors();

    expect(SupplierVoucher::query()->sole()->status)->toBe($expectedStatus);
})->with([
    'invoice pending payment' => [SupplierVoucherType::Invoice, SupplierVoucherStatus::Pending],
    'debit note pending payment' => [SupplierVoucherType::DebitNote, SupplierVoucherStatus::Pending],
    'credit note available' => [SupplierVoucherType::CreditNote, SupplierVoucherStatus::PendingApplication],
]);

test('required header and at least one line are validated', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.vouchers.store'), [])
        ->assertSessionHasErrors([
            'supplier_id', 'type', 'letter', 'point_of_sale', 'number',
            'issue_date', 'total_amount', 'items',
        ]);
});

test('inactive suppliers and articles are rejected', function () {
    $supplier = Supplier::factory()->inactive()->create();
    $article = Article::factory()->inactive()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, $article))
        ->assertSessionHasErrors(['supplier_id', 'items.0.article_id']);
});

test('dates fiscal numbers and positive amounts are validated', function () {
    $supplier = Supplier::factory()->create();
    $article = Article::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, null, [
            'point_of_sale' => '12345',
            'number' => '12A',
            'issue_date' => today()->addDay()->toDateString(),
            'due_date' => today()->subDay()->toDateString(),
            'total_amount' => '0',
            'items' => [[
                'article_id' => $article->id,
                'description' => 'Ajuste',
                'quantity' => '0',
                'unit_of_measure' => 'unidad',
                'unit_price' => '-1',
                'line_total' => '0',
            ]],
        ]))
        ->assertSessionHasErrors([
            'point_of_sale', 'number', 'issue_date', 'due_date', 'total_amount',
            'items.0.quantity', 'items.0.unit_price', 'items.0.line_total',
        ]);
});

test('derived and removed fields cannot be supplied by the client', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, null, [
            'net_amount' => '100',
            'vat_amount' => '21',
            'other_taxes_amount' => '5',
            'status' => SupplierVoucherStatus::Paid->value,
            'outstanding_amount' => '0',
        ]))
        ->assertSessionHasErrors([
            'net_amount', 'vat_amount', 'other_taxes_amount', 'status', 'outstanding_amount',
        ]);
});

test('fiscal identity is unique by all five components', function () {
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
            ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, null, $variation))
            ->assertSessionHasNoErrors();
    }

    expect(SupplierVoucher::query()->count())->toBe(6);
});

test('show exposes saved header and all lines as read only data', function () {
    $supplier = Supplier::factory()->create(['business_name' => 'Lácteos del Sur']);
    $voucher = SupplierVoucher::factory()->create([
        'supplier_id' => $supplier->id,
        'point_of_sale' => '0007',
        'number' => '00001234',
        'total_amount' => '121.00',
    ]);
    SupplierVoucherItem::factory()->for($voucher)->create([
        'position' => 1,
        'description' => 'Leche entera según factura',
        'unit_of_measure' => 'caja',
        'line_total' => '100.00',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('purchasing.vouchers.show', $voucher))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/vouchers/show')
            ->where('voucher.supplier_business_name', 'Lácteos del Sur')
            ->where('voucher.formatted_number', 'A 0007-00001234')
            ->where('voucher.total_amount', '121.00')
            ->where('voucher.items_total', '100.00')
            ->where('voucher.difference_amount', '21.00')
            ->has('voucher.items', 1)
            ->where('voucher.items.0.description', 'Leche entera según factura')
            ->where('voucher.items.0.unit_of_measure', 'caja'));
});

test('listing supports overdue filter and exposes derived balance', function () {
    Carbon::setTestNow('2026-08-31 12:00:00');
    $supplier = Supplier::factory()->create(['business_name' => 'Proveedor vencido']);
    $overdue = SupplierVoucher::factory()->overdue()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '121.00',
    ]);
    SupplierVoucher::factory()->create(['due_date' => today()->addDay()]);

    $this->actingAs(User::factory()->create())
        ->get(route('purchasing.vouchers.index', ['only_overdue' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('vouchers.data', 1)
            ->where('vouchers.data.0.id', $overdue->id)
            ->where('vouchers.data.0.outstanding_amount', '121.00')
            ->where('vouchers.data.0.is_overdue', true));

    Carbon::setTestNow();
});

test('voucher can be annulled with audit data while preserving its lines', function () {
    Carbon::setTestNow('2026-09-06 15:30:00');
    $user = User::factory()->create();
    $voucher = SupplierVoucher::factory()->create();
    $item = SupplierVoucherItem::factory()->for($voucher)->create();

    $this->actingAs($user)
        ->post(route('purchasing.vouchers.annul', $voucher), ['reason' => 'Documento emitido por error'])
        ->assertSessionHasNoErrors();

    $voucher->refresh();
    expect($voucher->status)->toBe(SupplierVoucherStatus::Cancelled)
        ->and($voucher->annulled_by)->toBe($user->id)
        ->and($voucher->annulled_at?->toDateTimeString())->toBe('2026-09-06 15:30:00')
        ->and($voucher->annulment_reason)->toBe('Documento emitido por error')
        ->and($voucher->outstandingAmount())->toBe('0.00')
        ->and($item->fresh())->not->toBeNull();

    Carbon::setTestNow();
});

test('voucher with an active payment cannot be annulled', function () {
    $voucher = SupplierVoucher::factory()->create();
    PaymentOrderItem::factory()->forInvoice($voucher, '50.00')->create();

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.vouchers.annul', $voucher), ['reason' => 'Intento inválido'])
        ->assertSessionHasErrors(['status']);

    expect($voucher->fresh()->status)->toBe(SupplierVoucherStatus::Pending);
});

test('voucher has no editing deletion or PDF route', function () {
    expect(Route::has('purchasing.vouchers.edit'))->toBeFalse()
        ->and(Route::has('purchasing.vouchers.update'))->toBeFalse()
        ->and(Route::has('purchasing.vouchers.destroy'))->toBeFalse()
        ->and(Route::has('purchasing.vouchers.pdf'))->toBeFalse();
});

test('database protects fiscal uniqueness and positive totals', function () {
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
        'total_amount' => '0.00',
        'status' => SupplierVoucherStatus::Pending->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('supplier with vouchers cannot be physically deleted', function () {
    $supplier = Supplier::factory()->create();
    SupplierVoucher::factory()->create(['supplier_id' => $supplier->id]);

    $this->actingAs(User::factory()->create())
        ->delete(route('purchasing.suppliers.destroy', $supplier))
        ->assertSessionHasErrors(['supplier']);

    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
});

test('registering a credit note associated to an invoice lowers that invoice balance right away', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $invoice = SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '1000.00',
    ]);

    $this->actingAs($user)
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, null, [
            'type' => SupplierVoucherType::CreditNote->value,
            'number' => '999',
            'due_date' => null,
            'total_amount' => '400,00',
            'associated_invoice_id' => $invoice->id,
            'associated_amount' => '400,00',
        ]))
        ->assertSessionHasNoErrors();

    $creditNote = SupplierVoucher::query()->where('type', SupplierVoucherType::CreditNote)->sole();

    expect($invoice->fresh()->pendingBalance())->toBe('600.00')
        ->and($invoice->fresh()->status)->toBe(SupplierVoucherStatus::PartiallyPaid)
        ->and($creditNote->status)->toBe(SupplierVoucherStatus::Applied);

    $this->assertDatabaseHas('voucher_applications', [
        'source_voucher_id' => $creditNote->id,
        'target_voucher_id' => $invoice->id,
        'amount' => '400.00',
        'user_id' => $user->id,
    ]);
});

test('a credit note left free creates no application and stays available', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, null, [
            'type' => SupplierVoucherType::CreditNote->value,
            'due_date' => null,
        ]))
        ->assertSessionHasNoErrors();

    expect(SupplierVoucher::query()->sole()->status)->toBe(SupplierVoucherStatus::PendingApplication);
    $this->assertDatabaseCount('voucher_applications', 0);
});

test('the associated amount cannot exceed the credit note total', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '1000.00',
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, null, [
            'type' => SupplierVoucherType::CreditNote->value,
            'due_date' => null,
            'total_amount' => '300,00',
            'associated_invoice_id' => $invoice->id,
            'associated_amount' => '400,00',
        ]))
        ->assertSessionHasErrors(['associated_amount']);
});

test('the associated amount cannot exceed the invoice pending balance', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '250.00',
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, null, [
            'type' => SupplierVoucherType::CreditNote->value,
            'due_date' => null,
            'total_amount' => '400,00',
            'associated_invoice_id' => $invoice->id,
            'associated_amount' => '400,00',
        ]))
        ->assertSessionHasErrors(['associated_amount']);

    $this->assertDatabaseCount('voucher_applications', 0);
});

test('only a credit note may carry association fields', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierVoucher::factory()->invoice()->create(['supplier_id' => $supplier->id]);

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, null, [
            'type' => SupplierVoucherType::Invoice->value,
            'associated_invoice_id' => $invoice->id,
            'associated_amount' => '10,00',
        ]))
        ->assertSessionHasErrors(['associated_invoice_id', 'associated_amount']);
});

test('picking an invoice without an amount is rejected', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierVoucher::factory()->invoice()->create(['supplier_id' => $supplier->id]);

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.vouchers.store'), validSupplierVoucherData($supplier, null, [
            'type' => SupplierVoucherType::CreditNote->value,
            'due_date' => null,
            'associated_invoice_id' => $invoice->id,
        ]))
        ->assertSessionHasErrors(['associated_amount']);
});

test('associable invoices are the same supplier pending invoices only', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();

    $pending = SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '1000.00',
    ]);
    $paid = SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '500.00',
    ]);
    PaymentOrderItem::factory()->forInvoice($paid, '500.00')->create();
    SupplierVoucher::factory()->creditNote()->create(['supplier_id' => $supplier->id]);
    SupplierVoucher::factory()->invoice()->create(['supplier_id' => $otherSupplier->id]);

    $this->actingAs($user)
        ->getJson(route('purchasing.vouchers.associable-invoices', ['supplier_id' => $supplier->id]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $pending->id)
        ->assertJsonPath('0.outstanding_amount', '1000.00');
});

test('show exposes the applications on both the credit note and the invoice', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $invoice = SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '1000.00',
    ]);
    $creditNote = SupplierVoucher::factory()->creditNote()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '400.00',
    ]);
    app(AssociateCreditNoteToInvoice::class)
        ->handle($creditNote, $invoice->id, '400.00', $user->id);

    $this->actingAs($user)
        ->get(route('purchasing.vouchers.show', $creditNote))
        ->assertInertia(fn (Assert $page) => $page
            ->has('voucher.applications', 1)
            ->where('voucher.applications.0.direction', 'made')
            ->where('voucher.applications.0.counterparty_id', $invoice->id)
            ->where('voucher.applications.0.amount', '400.00')
            ->where('voucher.applications.0.user_name', $user->name));

    $this->actingAs($user)
        ->get(route('purchasing.vouchers.show', $invoice))
        ->assertInertia(fn (Assert $page) => $page
            ->has('voucher.applications', 1)
            ->where('voucher.applications.0.direction', 'received')
            ->where('voucher.applications.0.counterparty_id', $creditNote->id));
});
