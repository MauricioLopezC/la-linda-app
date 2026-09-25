<?php

use App\Enums\Pricing\PriceListChannel;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\User;

/**
 * End-to-end check of the HU-056 verification scenarios from the counter sale screen.
 * The online scenario is covered by ResolveArticlePriceTest: the counter screen never
 * opens online sales.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->general = PriceList::factory()->forChannel(PriceListChannel::General)->create(['name' => 'Lista General']);
    $this->mostrador = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create(['name' => 'Lista Mostrador']);
    $this->mayorista = PriceList::factory()->particular()->create(['name' => 'Mayorista']);

    $this->article = Article::factory()->create(['barcode' => '7790000000011']);
    $this->onlyInGeneral = Article::factory()->create(['barcode' => '7790000000028']);

    foreach ([[$this->general, '1100.00'], [$this->mostrador, '1000.00'], [$this->mayorista, '800.00']] as [$list, $price]) {
        PriceListItem::factory()->create(['price_list_id' => $list->id, 'article_id' => $this->article->id, 'price' => $price]);
    }

    PriceListItem::factory()->create(['price_list_id' => $this->general->id, 'article_id' => $this->onlyInGeneral->id, 'price' => '300.00']);

    Customer::factory()->defaultCustomer()->create();
    $this->pointOfSale = PointOfSale::factory()->create();
});

/**
 * Open a counter sale through HTTP and return it.
 */
function openCounterSale(PointOfSale $pointOfSale, ?Customer $customer = null): Sale
{
    test()->post(route('sales.sales.store'), [
        'point_of_sale_id' => $pointOfSale->id,
        'customer_id' => $customer?->id,
    ])->assertSessionHasNoErrors();

    return Sale::query()->latest('id')->firstOrFail();
}

test('a customer with a particular list gets the particular price', function () {
    $customer = Customer::factory()->create(['price_list_id' => $this->mayorista->id]);
    $sale = openCounterSale($this->pointOfSale, $customer);

    $this->post(route('sales.sales.items.store', $sale), ['code' => '7790000000011'])->assertSessionHasNoErrors();

    $this->get(route('sales.sales.show', $sale))->assertInertia(fn ($page) => $page
        ->where('sale.items.0.unit_price', '800.00')
        ->where('sale.items.0.price_origin_label', 'Particular: Mayorista')
        ->where('sale.total_amount', '800.00'));
});

test('Consumidor Final at the counter gets the mostrador price', function () {
    $sale = openCounterSale($this->pointOfSale);

    $this->post(route('sales.sales.items.store', $sale), ['code' => '7790000000011'])->assertSessionHasNoErrors();

    $this->get(route('sales.sales.show', $sale))->assertInertia(fn ($page) => $page
        ->where('sale.items.0.unit_price', '1000.00')
        ->where('sale.items.0.price_origin_label', 'Mostrador')
        ->where('sale.items.0.price_list_id', $this->mostrador->id));
});

test('an article missing from the mostrador list falls back to the general list', function () {
    $sale = openCounterSale($this->pointOfSale);

    $this->post(route('sales.sales.items.store', $sale), ['code' => '7790000000028'])->assertSessionHasNoErrors();

    $this->get(route('sales.sales.show', $sale))->assertInertia(fn ($page) => $page
        ->where('sale.items.0.unit_price', '300.00')
        ->where('sale.items.0.price_origin_label', 'General')
        ->where('sale.items.0.price_list_id', $this->general->id));
});
