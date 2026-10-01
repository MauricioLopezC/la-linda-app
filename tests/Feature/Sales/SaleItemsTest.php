<?php

use App\Enums\Catalog\ArticleStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Sales\CashSessionStatus;
use App\Models\Catalog\Article;
use App\Models\Catalog\UnitOfMeasure;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use App\Models\Pricing\VatRate;
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

test('an unknown code is rejected with a message that identifies it, keeping the sale open', function () {
    $this->post(route('sales.sales.items.store', $this->sale), ['code' => ' NO-EXISTE '])
        ->assertSessionHasErrors(['code' => 'No se encontró ningún artículo con el código "NO-EXISTE".']);

    expect(SaleItem::count())->toBe(0)
        ->and($this->sale->fresh()->acceptsChanges())->toBeTrue();
});

test('a scanned code matches the barcode before the internal code', function () {
    $byBarcode = articlePricedIn($this->mostradorList, '100.00', ['barcode' => 'X-100']);
    articlePricedIn($this->mostradorList, '200.00', ['internal_code' => 'X-100']);

    $this->post(route('sales.sales.items.store', $this->sale), ['code' => 'X-100'])
        ->assertSessionHasNoErrors();

    expect(SaleItem::sole()->article_id)->toBe($byBarcode->id);
});

test('a weighed article accepts 0.750 when added and when its quantity changes', function () {
    $kilo = UnitOfMeasure::factory()->create(['allows_decimal_quantity' => true]);
    $article = articlePricedIn($this->mostradorList, '2000.00', ['unit_of_measure_id' => $kilo->id]);

    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $article->id, 'quantity' => '0.750'])
        ->assertSessionHasNoErrors();

    $item = SaleItem::sole();

    expect($item->quantity)->toBe('0.750')
        ->and($item->line_total)->toBe('1500.00');

    $this->patch(route('sales.sales.items.update', [$this->sale, $item]), ['quantity' => '1.250'])
        ->assertSessionHasNoErrors();

    expect($item->fresh()->quantity)->toBe('1.250')
        ->and($this->sale->fresh()->total_amount)->toBe('2500.00');
});

test('an article sold by unit rejects a decimal quantity when its quantity changes', function () {
    $article = articlePricedIn($this->mostradorList, '100.00');
    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $article->id]);
    $item = SaleItem::sole();

    $this->patch(route('sales.sales.items.update', [$this->sale, $item]), ['quantity' => '1.5'])
        ->assertSessionHasErrors(['quantity']);

    expect($item->fresh()->quantity)->toBe('1.000');
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

test('a line freezes the article VAT rate and splits its total into net and VAT', function () {
    $reduced = VatRate::factory()->create(['percentage' => 10.5]);
    $article = articlePricedIn($this->mostradorList, '1250.40', ['vat_rate_id' => $reduced->id]);

    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $article->id])
        ->assertSessionHasNoErrors();

    $article->update(['vat_rate_id' => VatRate::factory()->create(['percentage' => 21])->id]);
    $item = SaleItem::sole()->fresh();

    // 1250.40 / 1.105 = 1131.583... → 1131.58; VAT = 1250.40 − 1131.58
    expect($item->vat_rate_id)->toBe($reduced->id)
        ->and($item->vat_rate)->toBe('10.50')
        ->and($item->net_amount)->toBe('1131.58')
        ->and($item->vat_amount)->toBe('118.82');
});

test('changing the quantity keeps net plus VAT equal to the line total', function () {
    $article = articlePricedIn($this->mostradorList, '99.99');
    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $article->id]);
    $item = SaleItem::sole();

    $this->patch(route('sales.sales.items.update', [$this->sale, $item]), ['quantity' => 7])
        ->assertSessionHasNoErrors();

    $item->refresh();

    // 7 × 99.99 = 699.93 → net 699.93 / 1.21 = 578.454... → 578.45
    expect($item->line_total)->toBe('699.93')
        ->and($item->net_amount)->toBe('578.45')
        ->and($item->vat_amount)->toBe('121.48');
});

test('an article without a VAT rate is rejected', function () {
    $article = articlePricedIn($this->mostradorList, '100.00', ['vat_rate_id' => null]);

    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $article->id])
        ->assertSessionHasErrors(['article_id']);

    expect(SaleItem::count())->toBe(0);
});

test('an article with an inactive VAT rate is rejected', function () {
    $inactiveVat = VatRate::factory()->create(['percentage' => 21, 'is_active' => false]);
    $article = articlePricedIn($this->mostradorList, '100.00', ['vat_rate_id' => $inactiveVat->id]);

    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $article->id])
        ->assertSessionHasErrors(['article_id']);

    expect(SaleItem::count())->toBe(0);
});

test('a sale with articles of different VAT rates discriminates net and VAT per line without rounding differences', function () {
    $standardVat = VatRate::factory()->create(['description' => 'IVA 21%', 'percentage' => 21.0]);
    $reducedVat = VatRate::factory()->create(['description' => 'IVA 10.5%', 'percentage' => 10.5]);

    $article21 = articlePricedIn($this->mostradorList, '121.00', ['vat_rate_id' => $standardVat->id]);
    $article105 = articlePricedIn($this->mostradorList, '110.50', ['vat_rate_id' => $reducedVat->id]);

    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $article21->id, 'quantity' => 2])
        ->assertSessionHasNoErrors();
    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $article105->id, 'quantity' => 1])
        ->assertSessionHasNoErrors();

    $item21 = $this->sale->items()->where('article_id', $article21->id)->sole();
    $item105 = $this->sale->items()->where('article_id', $article105->id)->sole();

    // Line 1 (21%): total = 242.00, net = 242.00 / 1.21 = 200.00, vat = 42.00
    expect($item21->line_total)->toBe('242.00')
        ->and($item21->net_amount)->toBe('200.00')
        ->and($item21->vat_amount)->toBe('42.00')
        ->and((float) $item21->net_amount + (float) $item21->vat_amount)->toBe(242.00);

    // Line 2 (10.5%): total = 110.50, net = 110.50 / 1.105 = 100.00, vat = 10.50
    expect($item105->line_total)->toBe('110.50')
        ->and($item105->net_amount)->toBe('100.00')
        ->and($item105->vat_amount)->toBe('10.50')
        ->and((float) $item105->net_amount + (float) $item105->vat_amount)->toBe(110.50);

    $sale = $this->sale->fresh();
    expect($sale->total_amount)->toBe('352.50')
        ->and($sale->netAmount())->toBe('300.00')
        ->and($sale->vatAmount())->toBe('52.50');

    $breakdown = $sale->getVatBreakdown();
    expect($breakdown)->toHaveCount(2)
        ->and($breakdown[0]['vat_rate'])->toBe('21.00')
        ->and($breakdown[0]['net_amount'])->toBe('200.00')
        ->and($breakdown[0]['vat_amount'])->toBe('42.00')
        ->and($breakdown[0]['total_amount'])->toBe('242.00')
        ->and($breakdown[1]['vat_rate'])->toBe('10.50')
        ->and($breakdown[1]['net_amount'])->toBe('100.00')
        ->and($breakdown[1]['vat_amount'])->toBe('10.50')
        ->and($breakdown[1]['total_amount'])->toBe('110.50');
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

test('adding an article is rejected if the sale cash session is closed', function () {
    $article = articlePricedIn($this->mostradorList, '100.00');
    $sale = Sale::factory()->closedSession()->create();

    $this->post(route('sales.sales.items.store', $sale), ['article_id' => $article->id])
        ->assertSessionHasErrors(['article_id']);

    expect($sale->items()->count())->toBe(0);
});

test('updating line quantity is rejected if the sale cash session is closed', function () {
    $article = articlePricedIn($this->mostradorList, '100.00');
    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $article->id, 'quantity' => 2]);
    $item = SaleItem::sole();

    $this->sale->cashSession->update(['status' => CashSessionStatus::Closed, 'closed_at' => now()]);

    $this->patch(route('sales.sales.items.update', [$this->sale, $item]), ['quantity' => 5])
        ->assertSessionHasErrors(['quantity']);

    expect($item->fresh()->quantity)->toBe('2.000');
});

test('removing a line is rejected if the sale cash session is closed', function () {
    $article = articlePricedIn($this->mostradorList, '100.00');
    $this->post(route('sales.sales.items.store', $this->sale), ['article_id' => $article->id]);
    $item = SaleItem::sole();

    $this->sale->cashSession->update(['status' => CashSessionStatus::Closed, 'closed_at' => now()]);

    $this->delete(route('sales.sales.items.destroy', [$this->sale, $item]))
        ->assertSessionHasErrors(['sale']);

    expect($this->sale->items()->count())->toBe(1);
});
