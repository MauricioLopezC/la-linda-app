<?php

use App\Enums\Pricing\PriceListChannel;
use App\Models\Catalog\Article;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use App\Models\Sales\CashSession;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * Set the price of an article in a price list.
 */
function setArticlePrice(PriceList $priceList, Article $article, string $price): void
{
    PriceListItem::factory()->create([
        'price_list_id' => $priceList->id,
        'article_id' => $article->id,
        'price' => $price,
    ]);
}

test('selecting a specific price list re-prices every line on an open sale', function () {
    $mostrador = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $mayorista = PriceList::factory()->particular()->create(['name' => 'Mayorista']);
    $article = Article::factory()->create();

    setArticlePrice($mostrador, $article, '1000.00');
    setArticlePrice($mayorista, $article, '800.00');

    $sale = Sale::factory()->create();
    $this->post(route('sales.sales.items.store', $sale), ['article_id' => $article->id, 'quantity' => 2]);

    expect($sale->fresh()->total_amount)->toBe('2000.00')
        ->and($sale->fresh()->price_list_id)->toBeNull();

    $this->patch(route('sales.sales.price-list.update', $sale), ['price_list_id' => $mayorista->id])
        ->assertSessionHasNoErrors();

    $item = SaleItem::sole();

    expect($sale->fresh()->price_list_id)->toBe($mayorista->id)
        ->and($item->unit_price)->toBe('800.00')
        ->and($item->price_list_id)->toBe($mayorista->id)
        ->and($item->line_total)->toBe('1600.00')
        ->and($sale->fresh()->total_amount)->toBe('1600.00');
});

test('adding an article to a sale with an explicit price list uses that list first', function () {
    $mostrador = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $mayorista = PriceList::factory()->particular()->create(['name' => 'Mayorista Especial']);
    $article = Article::factory()->create();

    setArticlePrice($mostrador, $article, '1200.00');
    setArticlePrice($mayorista, $article, '950.00');

    $sale = Sale::factory()->create(['price_list_id' => $mayorista->id]);

    $this->post(route('sales.sales.items.store', $sale), ['article_id' => $article->id, 'quantity' => 1])
        ->assertSessionHasNoErrors();

    $item = SaleItem::sole();

    expect($item->unit_price)->toBe('950.00')
        ->and($item->price_list_id)->toBe($mayorista->id)
        ->and($sale->fresh()->total_amount)->toBe('950.00');
});

test('resetting price list to automatic (null) re-prices lines back through the default cascade', function () {
    $mostrador = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $mayorista = PriceList::factory()->particular()->create(['name' => 'Mayorista']);
    $article = Article::factory()->create();

    setArticlePrice($mostrador, $article, '1000.00');
    setArticlePrice($mayorista, $article, '750.00');

    $sale = Sale::factory()->create(['price_list_id' => $mayorista->id]);
    $this->post(route('sales.sales.items.store', $sale), ['article_id' => $article->id, 'quantity' => 2]);

    expect($sale->fresh()->total_amount)->toBe('1500.00');

    $this->patch(route('sales.sales.price-list.update', $sale), ['price_list_id' => null])
        ->assertSessionHasNoErrors();

    $item = SaleItem::sole();

    expect($sale->fresh()->price_list_id)->toBeNull()
        ->and($item->unit_price)->toBe('1000.00')
        ->and($item->price_list_id)->toBe($mostrador->id)
        ->and($item->line_total)->toBe('2000.00')
        ->and($sale->fresh()->total_amount)->toBe('2000.00');
});

test('changing price list on an empty sale succeeds', function () {
    $mayorista = PriceList::factory()->particular()->create();
    $sale = Sale::factory()->create();

    $this->patch(route('sales.sales.price-list.update', $sale), ['price_list_id' => $mayorista->id])
        ->assertSessionHasNoErrors();

    expect($sale->fresh()->price_list_id)->toBe($mayorista->id)
        ->and($sale->fresh()->total_amount)->toBe('0.00');
});

test('changing price list is rejected if the sale or session does not accept changes', function () {
    $mayorista = PriceList::factory()->particular()->create();
    $closedSession = CashSession::factory()->closed()->create();
    $sale = Sale::factory()->create(['cash_session_id' => $closedSession->id]);

    $this->patch(route('sales.sales.price-list.update', $sale), ['price_list_id' => $mayorista->id])
        ->assertSessionHasErrors(['price_list_id']);
});

test('changing price list is rejected if the list is inactive or expired', function () {
    $inactive = PriceList::factory()->inactive()->create();
    $expired = PriceList::factory()->create([
        'valid_from' => Carbon::today()->subMonths(2),
        'valid_to' => Carbon::yesterday(),
    ]);
    $sale = Sale::factory()->create();

    $this->patch(route('sales.sales.price-list.update', $sale), ['price_list_id' => $inactive->id])
        ->assertSessionHasErrors(['price_list_id']);

    $this->patch(route('sales.sales.price-list.update', $sale), ['price_list_id' => $expired->id])
        ->assertSessionHasErrors(['price_list_id']);
});

test('sale screen lists active and currently valid price lists', function () {
    $validList = PriceList::factory()->particular()->create(['name' => 'Lista Vigente']);
    $inactiveList = PriceList::factory()->inactive()->create(['name' => 'Lista Inactiva']);
    $expiredList = PriceList::factory()->create([
        'name' => 'Lista Expirada',
        'valid_from' => Carbon::today()->subMonths(2),
        'valid_to' => Carbon::yesterday(),
    ]);

    $sale = Sale::factory()->create();

    $response = $this->get(route('sales.sales.show', $sale));

    $response->assertInertia(fn ($page) => $page
        ->component('sales/sales/show')
        ->has('priceLists')
        ->where('priceLists', fn ($lists) => collect($lists)->contains('id', $validList->id)
            && ! collect($lists)->contains('id', $inactiveList->id)
            && ! collect($lists)->contains('id', $expiredList->id)
        )
    );
});
