<?php

use App\Actions\Sales\ConfirmSalePayment;
use App\Enums\Sales\CashSessionStatus;
use App\Enums\Sales\InvoiceType;
use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Pricing\VatRate;
use App\Models\Sales\CashSession;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Event;

/**
 * Confirm a counter sale with one line at 21 % and one at 10,5 %, paid with cash (with change)
 * and card, so ConfirmSalePayment issues its invoice (HU-042).
 *
 * @return array{0: User, 1: Sale, 2: Invoice}
 */
function confirmSaleWithTwoRatesAndTwoPayments(Customer $customer): array
{
    $user = User::factory()->create(['name' => 'Cajero Uno']);
    $pointOfSale = PointOfSale::factory()->create(['number' => 1]);
    $cashSession = CashSession::factory()->create([
        'point_of_sale_id' => $pointOfSale->id,
        'user_id' => $user->id,
        'status' => CashSessionStatus::Open,
    ]);

    $sale = Sale::factory()->create([
        'point_of_sale_id' => $pointOfSale->id,
        'user_id' => $user->id,
        'cash_session_id' => $cashSession->id,
        'customer_id' => $customer->id,
        'channel' => SaleChannel::Mostrador,
        'status' => SaleStatus::Open,
    ]);

    $generalRate = VatRate::factory()->state(['percentage' => 21])->create();
    $reducedRate = VatRate::factory()->state(['percentage' => 10.5])->create();

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'article_id' => Article::factory()->create(['description' => 'Aceite girasol 1,5 L', 'vat_rate_id' => $generalRate->id])->id,
        'quantity' => '2.000',
        'unit_price' => '1210.00',
        'vat_rate_id' => $generalRate->id,
        'vat_rate' => '21.00',
        'net_amount' => '2000.00',
        'vat_amount' => '420.00',
        'line_total' => '2420.00',
    ]);

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'article_id' => Article::factory()->create(['description' => 'Pan francés', 'vat_rate_id' => $reducedRate->id])->id,
        'quantity' => '1.500',
        'unit_price' => '1105.00',
        'vat_rate_id' => $reducedRate->id,
        'vat_rate' => '10.50',
        'net_amount' => '1500.00',
        'vat_amount' => '157.50',
        'line_total' => '1657.50',
    ]);

    $sale->recalculateTotal();

    $cash = PaymentMethod::factory()->cash()->create(['name' => 'Efectivo']);
    $card = PaymentMethod::factory()->card()->create(['name' => 'Tarjeta de débito']);

    app(ConfirmSalePayment::class)->handle($sale, [
        ['payment_method_id' => $cash->id, 'amount' => '1657.50', 'tendered_amount' => '2000.00'],
        ['payment_method_id' => $card->id, 'amount' => '2420.00'],
    ], $user);

    return [$user, $sale->refresh(), $sale->invoice()->firstOrFail()];
}

/**
 * Request the invoice PDF and return the HTML the PDF was rendered from: the PDF itself is
 * compressed, so its text cannot be asserted directly.
 */
function invoicePdfHtml(User $user, Sale $sale): string
{
    $renderedView = null;

    Event::listen('composing: pdf.sales.invoice', function (View $view) use (&$renderedView): void {
        $renderedView = $view;
    });

    test()->actingAs($user)
        ->get(route('sales.sales.invoice.pdf', $sale))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($renderedView)->not->toBeNull();

    return $renderedView->render();
}

test('the invoice pdf is shown inline to print and downloaded on request', function () {
    [$user, $sale] = confirmSaleWithTwoRatesAndTwoPayments(Customer::factory()->consumidorFinal()->create());

    $this->actingAs($user)
        ->get(route('sales.sales.invoice.pdf', $sale))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=Factura_B_0001-00000001.pdf');

    $this->actingAs($user)
        ->get(route('sales.sales.invoice.pdf', ['sale' => $sale, 'download' => 1]))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=Factura_B_0001-00000001.pdf');
});

test('a sale without an issued invoice has no pdf', function () {
    $sale = Sale::factory()->create(['status' => SaleStatus::Open]);

    $this->actingAs(User::factory()->create())
        ->get(route('sales.sales.invoice.pdf', $sale))
        ->assertNotFound();
});

test('guests cannot get the invoice pdf', function () {
    [, $sale] = confirmSaleWithTwoRatesAndTwoPayments(Customer::factory()->consumidorFinal()->create());

    $this->get(route('sales.sales.invoice.pdf', $sale))
        ->assertRedirect(route('login'));
});

test('factura A shows issuer, voucher, VAT per rate, both payments and the change, matching the sale', function () {
    $customer = Customer::factory()->responsableInscripto()->create([
        'name' => 'Distribuidora Norte SRL',
        'id_number' => '30500858628',
        'address' => 'Belgrano 500, Salta',
    ]);

    [$user, $sale, $invoice] = confirmSaleWithTwoRatesAndTwoPayments($customer);

    expect($invoice->type)->toBe(InvoiceType::A)
        ->and($sale->total_amount)->toBe('4077.50');

    $html = invoicePdfHtml($user, $sale);

    expect($html)
        ->toContain('COD. 001')
        ->toContain('0001')
        ->toContain('00000001')
        ->toContain(config('invoicing.issuer.name'))
        ->toContain(config('invoicing.issuer.cuit'))
        ->toContain(config('invoicing.issuer.activity_start_date'))
        ->toContain('Distribuidora Norte SRL')
        ->toContain('CUIT 30-50085862-8')
        ->toContain('IVA Responsable Inscripto')
        ->toContain('Aceite girasol 1,5 L')
        ->toContain('Pan francés')
        ->toContain('Importe neto gravado')
        ->toContain('$ 3.500,00')
        ->toContain('IVA 21 %')
        ->toContain('$ 420,00')
        ->toContain('IVA 10,5 %')
        ->toContain('$ 157,50')
        ->toContain('$ 4.077,50')
        ->toContain('Efectivo')
        ->toContain('Entregado: $ 2.000,00')
        ->toContain('Vuelto: $ 342,50')
        ->toContain('Tarjeta de débito')
        ->toContain('Comprobante sin CAE')
        ->not->toContain('Transparencia Fiscal');
});

test('factura B shows the total with VAT included and the fiscal transparency legend', function () {
    [$user, $sale] = confirmSaleWithTwoRatesAndTwoPayments(Customer::factory()->consumidorFinal()->create());

    $html = invoicePdfHtml($user, $sale);

    expect($html)
        ->toContain('COD. 006')
        ->toContain('$ 4.077,50')
        ->toContain('Régimen de Transparencia Fiscal al Consumidor (Ley 27.743)')
        ->toContain('IVA contenido: $ 577,50')
        ->toContain('Comprobante sin CAE')
        ->not->toContain('Importe neto gravado')
        ->not->toContain('IVA 21 %');
});

test('the pdf keeps the customer data frozen on the invoice', function () {
    $customer = Customer::factory()->responsableInscripto()->create(['address' => 'Belgrano 500, Salta']);

    [$user, $sale] = confirmSaleWithTwoRatesAndTwoPayments($customer);

    $customer->update(['address' => 'Mitre 999, Jujuy', 'name' => 'Otro Nombre SA']);

    $html = invoicePdfHtml($user, $sale);

    expect($html)
        ->toContain('Belgrano 500, Salta')
        ->not->toContain('Mitre 999, Jujuy')
        ->not->toContain('Otro Nombre SA');
});

test('the VAT breakdown adds up the frozen lines per rate', function () {
    [, , $invoice] = confirmSaleWithTwoRatesAndTwoPayments(Customer::factory()->consumidorFinal()->create());

    expect($invoice->vatBreakdown())->toBe([
        ['vat_rate' => '21.00', 'net_amount' => '2000.00', 'vat_amount' => '420.00'],
        ['vat_rate' => '10.50', 'net_amount' => '1500.00', 'vat_amount' => '157.50'],
    ]);
});
