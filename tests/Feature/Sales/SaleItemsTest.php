<?php

use App\Enums\Catalog\ArticleStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Models\Catalog\Article;
use App\Models\Catalog\UnitOfMeasure;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->mostradorList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $this->sale = Sale::factory()->create();
});

/**
 * Create an article priced in the given list.
 */
function articlePricedIn(PriceList $priceList, string $price, array $attributes = []): Article
{
    $article = Article::factory()->create($attributes);

    PriceListItem::factory()->create([
        'price_list_id' => $priceList->id,
        'article_id' => $article->id,
        'price' => $price,
    ]);

    return $article;
}

test('scanning a barcode adds a line priced from the mostrador list', function () {
    $article = articlePricedIn($this->mostradorList, '1250.50', ['barcode' => '7790001112223']);

    $this->post(route('sales.sales.items.store', $this->sale), ['code' => ' 7790001112223 '])
        ->assertSessionHasNoErrors();

    $item = SaleItem::sole();

    expect($item->article_id)->toBe($article->id)
        ->and($item->quantity)->toBe('1.000')
        ->and($item->unit_price)->toBe('1250.50')
        ->and($item->price_list_id)->toBe($this->mostradorList->id)
        ->and($item->line_total)->toBe('1250.50')
        ->and($this->sale->fresh()->total_amount)->toBe('1250.50');
});

test('an article can be added by its internal code or by id', function () {
    $byCode = articlePricedIn($this->mostradorList, '100.00', ['internal_code' => 'ART-0042']);
    $byId = articlePricedIn($this->mostradorList, '200.00');

    $this->post(route('sales.sales.items.store', $this->sale), ['code' => 'art-0042'])->assertSessionHasNoErrors();
    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $byId->id])->assertSessionHasNoErrors();

    expect($this->sale->items()->pluck('article_id')->all())->toEqualCanonicalizing([$byCode->id, $byId->id])
        ->and($this->sale->fresh()->total_amount)->toBe('300.00');
});

test('an unknown code is rejected', function () {
    $this->post(route('sales.sales.items.store', $this->sale), ['code' => 'NO-EXISTE'])
        ->assertSessionHasErrors(['code']);

    expect(SaleItem::count())->toBe(0);
});

test('scanning the same article again adds to its quantity instead of a new line', function () {
    articlePricedIn($this->mostradorList, '150.00', ['barcode' => '111']);

    $this->post(route('sales.sales.items.store', $this->sale), ['code' => '111']);
    $this->post(route('sales.sales.items.store', $this->sale), ['code' => '111']);

    $item = SaleItem::sole();

    expect($item->quantity)->toBe('2.000')
        ->and($item->line_total)->toBe('300.00')
        ->and($this->sale->fresh()->total_amount)->toBe('300.00');
});

test('an article without a price in any list is rejected and no line is added', function () {
    Article::factory()->create(['barcode' => '999']);

    $this->post(route('sales.sales.items.store', $this->sale), ['code' => '999'])
        ->assertSessionHasErrors(['code']);

    expect(SaleItem::count())->toBe(0);
});

test('an inactive article is rejected', function () {
    $article = articlePricedIn($this->mostradorList, '100.00');
    $article->update(['status' => ArticleStatus::Inactive]);

    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $article->id])
        ->assertSessionHasErrors(['article_id']);
});

test('a discarded sale does not accept lines', function () {
    $article = articlePricedIn($this->mostradorList, '100.00');
    $sale = Sale::factory()->discarded()->create();

    $this->post(route('sales.sales.items.store', $sale), ['article_id' => $article->id])
        ->assertSessionHasErrors(['article_id']);
});

test('a weighed article accepts decimal quantities and rounds the line total to cents', function () {
    $kilo = UnitOfMeasure::factory()->create(['allows_decimal_quantity' => true]);
    $article = articlePricedIn($this->mostradorList, '3333.33', ['unit_of_measure_id' => $kilo->id]);

    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $article->id, 'quantity' => '0.755'])
        ->assertSessionHasNoErrors();

    // 0.755 × 3333.33 = 2516.664... → 2516.66
    expect(SaleItem::sole()->line_total)->toBe('2516.66')
        ->and($this->sale->fresh()->total_amount)->toBe('2516.66');
});

test('an article sold by unit rejects decimal quantities', function () {
    $article = articlePricedIn($this->mostradorList, '100.00');

    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $article->id, 'quantity' => '1.5'])
        ->assertSessionHasErrors(['quantity']);
});

test('changing the quantity recalculates the line and the sale total', function () {
    $article = articlePricedIn($this->mostradorList, '80.00');
    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $article->id]);
    $item = SaleItem::sole();

    $this->patch(route('sales.sales.items.update', [$this->sale, $item]), ['quantity' => 5])
        ->assertSessionHasNoErrors();

    expect($item->fresh()->line_total)->toBe('400.00')
        ->and($this->sale->fresh()->total_amount)->toBe('400.00');
});

test('a zero quantity is rejected', function () {
    $article = articlePricedIn($this->mostradorList, '80.00');
    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $article->id]);

    $this->patch(route('sales.sales.items.update', [$this->sale, SaleItem::sole()]), ['quantity' => 0])
        ->assertSessionHasErrors(['quantity']);
});

test('removing a line recalculates the sale total', function () {
    $first = articlePricedIn($this->mostradorList, '80.00');
    $second = articlePricedIn($this->mostradorList, '20.00');
    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $first->id]);
    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $second->id]);

    $firstItem = $this->sale->items()->where('article_id', $first->id)->sole();

    $this->delete(route('sales.sales.items.destroy', [$this->sale, $firstItem]))->assertSessionHasNoErrors();

    expect($this->sale->items()->count())->toBe(1)
        ->and($this->sale->fresh()->total_amount)->toBe('20.00');
});

test('a line of another sale cannot be changed through this sale', function () {
    $otherItem = SaleItem::factory()->create();

    $this->patch(route('sales.sales.items.update', [$this->sale, $otherItem]), ['quantity' => 3])
        ->assertNotFound();
});

test('the line keeps its price when the list price changes while the sale is open', function () {
    $article = articlePricedIn($this->mostradorList, '100.00');
    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $article->id]);

    PriceListItem::query()->where('article_id', $article->id)->update(['price' => '500.00']);
    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $article->id]);

    expect(SaleItem::sole()->unit_price)->toBe('100.00')
        ->and($this->sale->fresh()->total_amount)->toBe('200.00');
});
