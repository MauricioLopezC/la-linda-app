<?php

use App\Actions\Sales\ConfirmSalePayment;
use App\Actions\Sales\IssueInvoice;
use App\Enums\Customers\CustomerIdType;
use App\Enums\Customers\CustomerTaxCondition;
use App\Enums\Sales\CashSessionStatus;
use App\Enums\Sales\InvoiceType;
use App\Enums\Sales\PaymentMethodKind;
use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Pricing\VatRate;
use App\Models\Sales\CashSession;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/**
 * Setup a confirmed sale with items and point of sale.
 *
 * @return array{0: User, 1: Sale, 2: CashSession, 3: PointOfSale}
 */
function createConfirmedSale(
    string $totalAmount = '2420.00',
    ?Customer $customer = null,
    ?PointOfSale $pointOfSale = null,
    ?CashSession $cashSession = null
): array {
    $user = User::factory()->create();
    $pointOfSale ??= PointOfSale::factory()->create(['number' => 1]);
    $cashSession ??= CashSession::factory()->create([
        'point_of_sale_id' => $pointOfSale->id,
        'user_id' => $user->id,
        'status' => CashSessionStatus::Open,
    ]);

    $customer ??= Customer::factory()->consumidorFinal()->create();

    $sale = Sale::factory()->create([
        'point_of_sale_id' => $pointOfSale->id,
        'user_id' => $user->id,
        'cash_session_id' => $cashSession->id,
        'customer_id' => $customer->id,
        'channel' => SaleChannel::Mostrador,
        'status' => SaleStatus::Confirmed,
        'confirmed_at' => now(),
        'total_amount' => $totalAmount,
    ]);

    $vatRate = VatRate::factory()->state(['percentage' => 21])->create();
    $article = Article::factory()->create(['vat_rate_id' => $vatRate->id]);

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'article_id' => $article->id,
        'quantity' => '2.000',
        'unit_price' => '1210.00',
        'vat_rate_id' => $vatRate->id,
        'vat_rate' => '21.00',
        'net_amount' => '2000.00',
        'vat_amount' => '420.00',
        'line_total' => $totalAmount,
    ]);

    $sale->recalculateTotal();

    return [$user, $sale, $cashSession, $pointOfSale];
}

test('two sales confirmed on POS 1 emit correlative Factura B 0001-00000001 and 0001-00000002', function () {
    $pos1 = PointOfSale::factory()->create(['number' => 1]);
    $session = CashSession::factory()->create([
        'point_of_sale_id' => $pos1->id,
        'status' => CashSessionStatus::Open,
    ]);
    $cfCustomer = Customer::factory()->consumidorFinal()->create();

    [$user1, $sale1] = createConfirmedSale('1210.00', $cfCustomer, $pos1, $session);
    [$user2, $sale2] = createConfirmedSale('2420.00', $cfCustomer, $pos1, $session);

    $action = app(IssueInvoice::class);

    $invoice1 = $action->handle($sale1, $user1);
    $invoice2 = $action->handle($sale2, $user2);

    expect($invoice1->type)->toBe(InvoiceType::B)
        ->and($invoice1->number)->toBe(1)
        ->and($invoice1->formattedNumber())->toBe('0001-00000001')
        ->and($invoice1->voucherLabel())->toBe('Factura B 0001-00000001');

    expect($invoice2->type)->toBe(InvoiceType::B)
        ->and($invoice2->number)->toBe(2)
        ->and($invoice2->formattedNumber())->toBe('0001-00000002')
        ->and($invoice2->voucherLabel())->toBe('Factura B 0001-00000002');
});

test('a sale to a responsable inscripto on POS 1 emits Factura A with independent numbering sequence', function () {
    $pos1 = PointOfSale::factory()->create(['number' => 1]);
    $session = CashSession::factory()->create([
        'point_of_sale_id' => $pos1->id,
        'status' => CashSessionStatus::Open,
    ]);
    $cfCustomer = Customer::factory()->consumidorFinal()->create();
    $riCustomer = Customer::factory()->responsableInscripto()->create();

    [$user1, $saleB] = createConfirmedSale('1210.00', $cfCustomer, $pos1, $session);
    [$user2, $saleA] = createConfirmedSale('2420.00', $riCustomer, $pos1, $session);

    $action = app(IssueInvoice::class);

    $invoiceB = $action->handle($saleB, $user1);
    $invoiceA = $action->handle($saleA, $user2);

    expect($invoiceB->type)->toBe(InvoiceType::B)
        ->and($invoiceB->number)->toBe(1)
        ->and($invoiceB->formattedNumber())->toBe('0001-00000001');

    expect($invoiceA->type)->toBe(InvoiceType::A)
        ->and($invoiceA->number)->toBe(1)
        ->and($invoiceA->formattedNumber())->toBe('0001-00000001')
        ->and($invoiceA->voucherLabel())->toBe('Factura A 0001-00000001');
});

test('independent numbering per point of sale: POS 2 has its own sequence', function () {
    $pos1 = PointOfSale::factory()->create(['number' => 1]);
    $pos2 = PointOfSale::factory()->create(['number' => 2]);
    $cfCustomer = Customer::factory()->consumidorFinal()->create();

    [$user1, $salePos1] = createConfirmedSale('1210.00', $cfCustomer, $pos1);
    [$user2, $salePos2] = createConfirmedSale('1210.00', $cfCustomer, $pos2);

    $action = app(IssueInvoice::class);

    $invoicePos1 = $action->handle($salePos1, $user1);
    $invoicePos2 = $action->handle($salePos2, $user2);

    expect($invoicePos1->formattedNumber())->toBe('0001-00000001');
    expect($invoicePos2->formattedNumber())->toBe('0002-00000001');
});

test('cannot issue invoice on an open or discarded sale', function () {
    $user = User::factory()->create();
    $saleOpen = Sale::factory()->create(['status' => SaleStatus::Open]);
    SaleItem::factory()->create(['sale_id' => $saleOpen->id]);

    $action = app(IssueInvoice::class);

    expect(fn () => $action->handle($saleOpen, $user))
        ->toThrow(ValidationException::class, 'Solo se puede emitir factura sobre una venta confirmada.');

    $saleDiscarded = Sale::factory()->discarded()->create();
    SaleItem::factory()->create(['sale_id' => $saleDiscarded->id]);

    expect(fn () => $action->handle($saleDiscarded, $user))
        ->toThrow(ValidationException::class, 'Solo se puede emitir factura sobre una venta confirmada.');
});

test('issued invoice keeps its point of sale number after the point of sale is renumbered', function () {
    [$user, $sale, $session, $pointOfSale] = createConfirmedSale();
    $invoice = app(IssueInvoice::class)->handle($sale, $user);

    $pointOfSale->update(['number' => 2]);

    $invoice->refresh();
    expect($invoice->point_of_sale_number)->toBe(1)
        ->and($invoice->formattedNumber())->toBe('0001-00000001')
        ->and($invoice->voucherLabel())->toBe('Factura B 0001-00000001');

    [$nextUser, $nextSale] = createConfirmedSale('2420.00', null, $pointOfSale, $session);
    $nextInvoice = app(IssueInvoice::class)->handle($nextSale, $nextUser);

    expect($nextInvoice->point_of_sale_number)->toBe(2)
        ->and($nextInvoice->formattedNumber())->toBe('0002-00000002');
});

test('cannot issue more than one invoice per sale', function () {
    [$user, $sale] = createConfirmedSale();
    $action = app(IssueInvoice::class);

    $action->handle($sale, $user);

    expect(fn () => $action->handle($sale, $user))
        ->toThrow(ValidationException::class, 'La venta ya cuenta con una factura emitida.');
});

test('cannot issue invoice for a sale without articles', function () {
    $user = User::factory()->create();
    $sale = Sale::factory()->confirmed()->create();

    $action = app(IssueInvoice::class);

    expect(fn () => $action->handle($sale, $user))
        ->toThrow(ValidationException::class, 'No se puede emitir factura para una venta sin artículos.');
});

test('factura A requires customer to be responsable inscripto with valid CUIT', function () {
    $pos = PointOfSale::factory()->create(['number' => 1]);
    $invalidRiCustomer = Customer::factory()->create([
        'tax_condition' => CustomerTaxCondition::ResponsibleInscripto,
        'id_type' => CustomerIdType::Dni,
        'id_number' => '12345678',
    ]);

    [$user, $sale] = createConfirmedSale('1000.00', $invalidRiCustomer, $pos);
    $action = app(IssueInvoice::class);

    expect(fn () => $action->handle($sale, $user))
        ->toThrow(ValidationException::class, 'La Factura A exige que el cliente sea responsable inscripto y tenga CUIT cargado.');
});

test('customer fiscal data and amounts are completely frozen upon invoice issuance', function () {
    $riCustomer = Customer::factory()->responsableInscripto()->create([
        'name' => 'Comercio Original S.R.L.',
        'address' => 'Calle Falsa 123',
    ]);

    [$user, $sale] = createConfirmedSale('2420.00', $riCustomer);
    $action = app(IssueInvoice::class);

    $invoice = $action->handle($sale, $user);

    expect($invoice->customer_name)->toBe('Comercio Original S.R.L.')
        ->and($invoice->customer_address)->toBe('Calle Falsa 123')
        ->and($invoice->customer_tax_condition)->toBe('responsable_inscripto')
        ->and($invoice->customer_id_number)->toBe($riCustomer->id_number)
        ->and((float) $invoice->net_amount)->toBe(2000.00)
        ->and((float) $invoice->vat_amount)->toBe(420.00)
        ->and((float) $invoice->total_amount)->toBe(2420.00);

    // Update customer afterwards in database
    $riCustomer->update([
        'name' => 'Nuevo Nombre Modificado S.A.',
        'address' => 'Avenida Siempre Viva 742',
        'tax_condition' => CustomerTaxCondition::ConsumidorFinal,
    ]);

    // Fresh invoice must keep the original frozen data
    $freshInvoice = $invoice->fresh();
    expect($freshInvoice->customer_name)->toBe('Comercio Original S.R.L.')
        ->and($freshInvoice->customer_address)->toBe('Calle Falsa 123')
        ->and($freshInvoice->customer_tax_condition)->toBe('responsable_inscripto');
});

test('invoice items freeze description, price, VAT rate and amounts independently from catalog changes', function () {
    [$user, $sale] = createConfirmedSale('2420.00');
    $saleItem = $sale->items()->first();
    $article = $saleItem->article;

    $action = app(IssueInvoice::class);
    $invoice = $action->handle($sale, $user);

    $invoiceItem = $invoice->items()->first();
    expect($invoiceItem)->not->toBeNull()
        ->and($invoiceItem->description)->toBe($article->description)
        ->and((float) $invoiceItem->quantity)->toBe(2.0)
        ->and((float) $invoiceItem->unit_price)->toBe(1210.00)
        ->and((float) $invoiceItem->vat_rate)->toBe(21.00)
        ->and((float) $invoiceItem->net_amount)->toBe(2000.00)
        ->and((float) $invoiceItem->vat_amount)->toBe(420.00)
        ->and((float) $invoiceItem->line_total)->toBe(2420.00);

    // Change article description in catalog
    $article->update(['description' => 'Descripción Completamente Diferente']);

    $freshInvoiceItem = $invoiceItem->fresh();
    expect($freshInvoiceItem->description)->not->toBe('Descripción Completamente Diferente');
});

test('automatic invoice issuance is triggered when ConfirmSalePayment runs', function () {
    $user = User::factory()->create();
    $pointOfSale = PointOfSale::factory()->create(['number' => 1]);
    $cashSession = CashSession::factory()->create([
        'point_of_sale_id' => $pointOfSale->id,
        'user_id' => $user->id,
        'status' => CashSessionStatus::Open,
    ]);

    $customer = Customer::factory()->consumidorFinal()->create();

    $sale = Sale::factory()->create([
        'point_of_sale_id' => $pointOfSale->id,
        'user_id' => $user->id,
        'cash_session_id' => $cashSession->id,
        'customer_id' => $customer->id,
        'channel' => SaleChannel::Mostrador,
        'status' => SaleStatus::Open,
        'total_amount' => '1210.00',
    ]);

    $vatRate = VatRate::factory()->state(['percentage' => 21])->create();
    $article = Article::factory()->create(['vat_rate_id' => $vatRate->id]);

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'article_id' => $article->id,
        'quantity' => '1.000',
        'unit_price' => '1210.00',
        'vat_rate_id' => $vatRate->id,
        'vat_rate' => '21.00',
        'net_amount' => '1000.00',
        'vat_amount' => '210.00',
        'line_total' => '1210.00',
    ]);

    $sale->recalculateTotal();

    $cashMethod = PaymentMethod::factory()->create([
        'kind' => PaymentMethodKind::Cash,
        'is_active' => true,
    ]);

    $confirmedSale = app(ConfirmSalePayment::class)->handle($sale, [
        [
            'payment_method_id' => $cashMethod->id,
            'amount' => 1210.00,
            'tendered_amount' => 1210.00,
        ],
    ], $user);

    expect($confirmedSale->status)->toBe(SaleStatus::Confirmed);

    $invoice = Invoice::query()->where('sale_id', $sale->id)->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->type)->toBe(InvoiceType::B)
        ->and($invoice->number)->toBe(1)
        ->and($invoice->formattedNumber())->toBe('0001-00000001')
        ->and($invoice->items)->toHaveCount(1);
});

test('failed invoice issuance rolls back confirmation and movements and a retry keeps the next number', function () {
    [$user, $sale] = createConfirmedSale();
    $sale->update(['status' => SaleStatus::Open, 'confirmed_at' => null]);
    SaleItem::factory()->create(['sale_id' => $sale->id]);
    $sale->recalculateTotal();
    $cashMethod = PaymentMethod::factory()->create([
        'kind' => PaymentMethodKind::Cash,
        'is_active' => true,
    ]);
    $payments = [[
        'payment_method_id' => $cashMethod->id,
        'amount' => $sale->total_amount,
        'tendered_amount' => $sale->total_amount,
    ]];

    $createdItems = 0;
    Event::listen('eloquent.creating: '.InvoiceItem::class, function () use (&$createdItems): void {
        $createdItems++;

        if ($createdItems === 2) {
            throw new RuntimeException('Invoice item issuance failed');
        }
    });

    expect(fn () => app(ConfirmSalePayment::class)->handle($sale, $payments, $user))
        ->toThrow(RuntimeException::class, 'Invoice item issuance failed');

    $sale->refresh();
    expect($createdItems)->toBe(2)
        ->and($sale->status)->toBe(SaleStatus::Open)
        ->and($sale->confirmed_at)->toBeNull()
        ->and($sale->invoice()->exists())->toBeFalse()
        ->and(InvoiceItem::query()->count())->toBe(0)
        ->and($sale->cashMovements()->exists())->toBeFalse()
        ->and($sale->stockMovement()->exists())->toBeFalse();

    $confirmedSale = app(ConfirmSalePayment::class)->handle($sale, $payments, $user);

    expect($confirmedSale->status)->toBe(SaleStatus::Confirmed)
        ->and($confirmedSale->invoice->formattedNumber())->toBe('0001-00000001')
        ->and($confirmedSale->invoice->items)->toHaveCount(2)
        ->and($confirmedSale->cashMovements)->toHaveCount(1)
        ->and($confirmedSale->stockMovement)->not->toBeNull();
});
