<?php

use App\Enums\Pricing\PriceListChannel;
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
