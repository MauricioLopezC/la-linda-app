<?php

use App\Enums\Customers\CustomerIdType;
use App\Enums\Customers\CustomerTaxCondition;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Sales\InvoiceType;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * Set the price of an article in a price list.
 */
function priceArticleInList(PriceList $priceList, Article $article, string $price): void
{
    PriceListItem::factory()->create([
        'price_list_id' => $priceList->id,
        'article_id' => $article->id,
        'price' => $price,
    ]);
}

test('switching to a customer with a particular list re-prices every line', function () {
    $mostrador = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $mayorista = PriceList::factory()->particular()->create(['name' => 'Mayorista']);
    $article = Article::factory()->create();
    priceArticleInList($mostrador, $article, '1000.00');
    priceArticleInList($mayorista, $article, '800.00');

    $customer = Customer::factory()->create(['price_list_id' => $mayorista->id]);
    $sale = Sale::factory()->create();
    $this->post(route('sales.sales.items.store', $sale), ['article_id' => $article->id, 'quantity' => 2]);

    expect($sale->fresh()->total_amount)->toBe('2000.00');

    $this->patch(route('sales.sales.customer.update', $sale), ['customer_id' => $customer->id])
        ->assertSessionHasNoErrors();

    $item = SaleItem::sole();

    expect($sale->fresh()->customer_id)->toBe($customer->id)
        ->and($item->unit_price)->toBe('800.00')
        ->and($item->price_list_id)->toBe($mayorista->id)
        ->and($item->line_total)->toBe('1600.00')
        ->and($sale->fresh()->total_amount)->toBe('1600.00');
});

test('switching back to Consumidor Final re-prices every line from the mostrador or general list', function () {
    $general = PriceList::factory()->forChannel(PriceListChannel::General)->create();
    $mostrador = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $mayorista = PriceList::factory()->particular()->create();
    $inMostrador = Article::factory()->create();
    $onlyInGeneral = Article::factory()->create();
    priceArticleInList($mayorista, $inMostrador, '800.00');
    priceArticleInList($mostrador, $inMostrador, '1000.00');
    priceArticleInList($mayorista, $onlyInGeneral, '250.00');
    priceArticleInList($general, $onlyInGeneral, '300.00');

    $wholesaleCustomer = Customer::factory()->create(['price_list_id' => $mayorista->id]);
    $consumidorFinal = Customer::factory()->defaultCustomer()->create();
    $sale = Sale::factory()->create(['customer_id' => $wholesaleCustomer->id]);
    $this->post(route('sales.sales.items.store', $sale), ['article_id' => $inMostrador->id]);
    $this->post(route('sales.sales.items.store', $sale), ['article_id' => $onlyInGeneral->id]);

    expect($sale->fresh()->total_amount)->toBe('1050.00');

    $this->patch(route('sales.sales.customer.update', $sale), ['customer_id' => $consumidorFinal->id])
        ->assertSessionHasNoErrors();

    $mostradorLine = $sale->items()->where('article_id', $inMostrador->id)->sole();
    $generalLine = $sale->items()->where('article_id', $onlyInGeneral->id)->sole();

    expect($mostradorLine->unit_price)->toBe('1000.00')
        ->and($mostradorLine->price_list_id)->toBe($mostrador->id)
        ->and($generalLine->unit_price)->toBe('300.00')
        ->and($generalLine->price_list_id)->toBe($general->id)
        ->and($sale->fresh()->total_amount)->toBe('1300.00');
});

test('the change is rejected as a whole if an article has no price for the new customer', function () {
    $mayorista = PriceList::factory()->particular()->create();
    $mostrador = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $onlyInMayorista = Article::factory()->create();
    $inBoth = Article::factory()->create();
    priceArticleInList($mayorista, $onlyInMayorista, '500.00');
    priceArticleInList($mayorista, $inBoth, '100.00');
    priceArticleInList($mostrador, $inBoth, '150.00');

    $wholesaleCustomer = Customer::factory()->create(['price_list_id' => $mayorista->id]);
    $consumidorFinal = Customer::factory()->defaultCustomer()->create();
    $sale = Sale::factory()->create(['customer_id' => $wholesaleCustomer->id]);
    $this->post(route('sales.sales.items.store', $sale), ['article_id' => $inBoth->id]);
    $this->post(route('sales.sales.items.store', $sale), ['article_id' => $onlyInMayorista->id]);

    $this->patch(route('sales.sales.customer.update', $sale), ['customer_id' => $consumidorFinal->id])
        ->assertSessionHasErrors(['customer_id']);

    expect($sale->fresh()->customer_id)->toBe($wholesaleCustomer->id)
        ->and($sale->items()->where('article_id', $inBoth->id)->sole()->unit_price)->toBe('100.00')
        ->and($sale->fresh()->total_amount)->toBe('600.00');
});

test('the customer of a discarded sale cannot be changed', function () {
    $sale = Sale::factory()->discarded()->create();

    $this->patch(route('sales.sales.customer.update', $sale), ['customer_id' => Customer::factory()->create()->id])
        ->assertSessionHasErrors(['customer_id']);
});

test('the customer of a sale with closed cash session cannot be changed', function () {
    $sale = Sale::factory()->closedSession()->create();

    $this->patch(route('sales.sales.customer.update', $sale), ['customer_id' => Customer::factory()->create()->id])
        ->assertSessionHasErrors(['customer_id']);
});

test('assigning a responsable inscripto with cuit determines Factura A and changing back to Consumidor Final passes to Factura B', function () {
    $sale = Sale::factory()->create();
    $consumidorFinal = Customer::factory()->defaultCustomer()->create();
    $sale->update(['customer_id' => $consumidorFinal->id]);

    expect($sale->fresh()->invoiceType())->toBe(InvoiceType::B);

    $responsableInscripto = Customer::factory()->responsableInscripto()->create();

    $this->patch(route('sales.sales.customer.update', $sale), ['customer_id' => $responsableInscripto->id])
        ->assertSessionHasNoErrors();

    expect($sale->fresh()->customer_id)->toBe($responsableInscripto->id)
        ->and($sale->fresh()->invoiceType())->toBe(InvoiceType::A);

    $this->patch(route('sales.sales.customer.update', $sale), ['customer_id' => $consumidorFinal->id])
        ->assertSessionHasNoErrors();

    expect($sale->fresh()->customer_id)->toBe($consumidorFinal->id)
        ->and($sale->fresh()->invoiceType())->toBe(InvoiceType::B);
});

test('assigning a monotributista or exento determines Factura B', function () {
    $sale = Sale::factory()->create();

    $monotributo = Customer::factory()->monotributo()->create();
    $this->patch(route('sales.sales.customer.update', $sale), ['customer_id' => $monotributo->id])
        ->assertSessionHasNoErrors();

    expect($sale->fresh()->invoiceType())->toBe(InvoiceType::B);

    $exento = Customer::factory()->create([
        'tax_condition' => CustomerTaxCondition::Exento,
        'id_type' => CustomerIdType::Cuit,
        'id_number' => '30500858628',
    ]);
    $this->patch(route('sales.sales.customer.update', $sale), ['customer_id' => $exento->id])
        ->assertSessionHasNoErrors();

    expect($sale->fresh()->invoiceType())->toBe(InvoiceType::B);
});

test('a responsable inscripto without cuit loaded is rejected when changing customer', function () {
    $sale = Sale::factory()->create();

    $riSinCuit = Customer::factory()->create([
        'tax_condition' => CustomerTaxCondition::ResponsibleInscripto,
        'id_type' => CustomerIdType::SinIdentificar,
        'id_number' => null,
    ]);

    $this->patch(route('sales.sales.customer.update', $sale), ['customer_id' => $riSinCuit->id])
        ->assertSessionHasErrors([
            'customer_id' => 'La factura A exige que el cliente sea responsable inscripto y tenga CUIT cargado.',
        ]);
});

test('an inactive customer cannot be assigned to a sale', function () {
    $sale = Sale::factory()->create();
    $inactiveCustomer = Customer::factory()->create(['is_active' => false]);

    $this->patch(route('sales.sales.customer.update', $sale), ['customer_id' => $inactiveCustomer->id])
        ->assertSessionHasErrors(['customer_id']);
});

test('the customer of a confirmed sale cannot be changed', function () {
    $sale = Sale::factory()->confirmed()->create();
    $customer = Customer::factory()->create();

    $this->patch(route('sales.sales.customer.update', $sale), ['customer_id' => $customer->id])
        ->assertSessionHasErrors(['customer_id']);
});
