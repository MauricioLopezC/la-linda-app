<?php

use App\Actions\Pricing\ResolveArticlePrice;
use App\Enums\Pricing\PriceListChannel;
use App\Exceptions\Pricing\ArticleNotPricedException;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Ensure the customers table has price_list_id even when HU-022 is not yet merged to master.
 * This makes the HU-056 test suite independently runnable on any branch.
 */
beforeEach(function (): void {
    if (! Schema::hasColumn('customers', 'price_list_id')) {
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('price_list_id')
                ->nullable()
                ->after('tax_condition')
                ->constrained('price_lists')
                ->nullOnDelete();
        });
    }
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Create an active + currently-valid price list of scope canal for the given channel,
 * with a price for the given article.
 */
function priceListForChannel(PriceListChannel $channel, Article $article, float $price = 100.00): PriceList
{
    $list = PriceList::factory()->forChannel($channel)->create([
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $list->id,
        'article_id' => $article->id,
        'price' => $price,
    ]);

    return $list;
}

/**
 * Create an active + currently-valid particular list with a price for the given article.
 */
function particularListForArticle(Article $article, float $price = 999.99): PriceList
{
    $list = PriceList::factory()->particular()->create([
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $list->id,
        'article_id' => $article->id,
        'price' => $price,
    ]);

    return $list;
}

// ---------------------------------------------------------------------------
// The three verification scenarios from the acceptance criteria
// ---------------------------------------------------------------------------

test('resuelve desde la lista particular del cliente cuando está activa y vigente', function (): void {
    $article = Article::factory()->create();
    $particularList = particularListForArticle($article, price: 999.99);

    // Also create a general fallback so the cascade has somewhere to go if the test breaks.
    priceListForChannel(PriceListChannel::General, $article, price: 50.00);

    $customer = Customer::factory()->create(['price_list_id' => $particularList->id]);

    $result = (new ResolveArticlePrice)->execute($article, PriceListChannel::Mostrador, $customer);

    expect($result->price_list_id)->toBe($particularList->id)
        ->and($result->unit_price)->toBe('999.99')
        ->and($result->price_list_scope)->toBe('particular');
});

test('resuelve desde la lista de canal mostrador cuando el cliente no tiene lista particular', function (): void {
    $article = Article::factory()->create();
    $mostradorList = priceListForChannel(PriceListChannel::Mostrador, $article, price: 200.00);
    priceListForChannel(PriceListChannel::General, $article, price: 50.00);

    $customer = Customer::factory()->create(['price_list_id' => null]);

    $result = (new ResolveArticlePrice)->execute($article, PriceListChannel::Mostrador, $customer);

    expect($result->price_list_id)->toBe($mostradorList->id)
        ->and($result->unit_price)->toBe('200.00')
        ->and($result->price_list_scope)->toBe('canal');
});

test('resuelve desde la lista de canal online cuando el cliente no tiene lista particular', function (): void {
    $article = Article::factory()->create();
    $onlineList = priceListForChannel(PriceListChannel::Online, $article, price: 350.00);
    priceListForChannel(PriceListChannel::General, $article, price: 50.00);

    $customer = Customer::factory()->create(['price_list_id' => null]);

    $result = (new ResolveArticlePrice)->execute($article, PriceListChannel::Online, $customer);

    expect($result->price_list_id)->toBe($onlineList->id)
        ->and($result->unit_price)->toBe('350.00');
});

// ---------------------------------------------------------------------------
// Fallback to general list
// ---------------------------------------------------------------------------

test('cae a lista general cuando el canal no tiene lista propia vigente', function (): void {
    $article = Article::factory()->create();
    $generalList = priceListForChannel(PriceListChannel::General, $article, price: 75.00);
    // No mostrador list created.

    $result = (new ResolveArticlePrice)->execute($article, PriceListChannel::Mostrador);

    expect($result->price_list_id)->toBe($generalList->id)
        ->and($result->unit_price)->toBe('75.00')
        ->and($result->price_list_scope)->toBe('canal');
});

test('cliente null omite el paso 1 y usa la lista del canal', function (): void {
    $article = Article::factory()->create();
    $mostradorList = priceListForChannel(PriceListChannel::Mostrador, $article, price: 180.00);
    priceListForChannel(PriceListChannel::General, $article, price: 50.00);

    $result = (new ResolveArticlePrice)->execute($article, PriceListChannel::Mostrador, customer: null);

    expect($result->price_list_id)->toBe($mostradorList->id);
});

// ---------------------------------------------------------------------------
// Hard rejection — no price found anywhere
// ---------------------------------------------------------------------------

test('lanza ArticleNotPricedException si el artículo no tiene precio en ninguna lista', function (): void {
    $article = Article::factory()->create();

    // General list exists but it does NOT have a price for this article.
    PriceList::factory()->forChannel(PriceListChannel::General)->create([
        'valid_from' => now()->subMonth()->toDateString(),
        'is_active' => true,
    ]);

    expect(fn () => (new ResolveArticlePrice)->execute($article, PriceListChannel::Mostrador))
        ->toThrow(ArticleNotPricedException::class);
});

// ---------------------------------------------------------------------------
// Expired / inactive / future lists are skipped
// ---------------------------------------------------------------------------

test('ignora la lista particular vencida y cae al canal', function (): void {
    $article = Article::factory()->create();
    $expiredParticular = PriceList::factory()->particular()->vencida()->create();

    PriceListItem::factory()->create([
        'price_list_id' => $expiredParticular->id,
        'article_id' => $article->id,
        'price' => 500.00,
    ]);

    $channelList = priceListForChannel(PriceListChannel::Mostrador, $article, price: 200.00);
    priceListForChannel(PriceListChannel::General, $article, price: 50.00);

    $customer = Customer::factory()->create(['price_list_id' => $expiredParticular->id]);

    $result = (new ResolveArticlePrice)->execute($article, PriceListChannel::Mostrador, $customer);

    expect($result->price_list_id)->toBe($channelList->id);
});

test('ignora la lista particular inactiva y cae al canal', function (): void {
    $article = Article::factory()->create();
    $inactiveParticular = PriceList::factory()->particular()->inactive()->create([
        'valid_from' => now()->subMonth()->toDateString(),
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $inactiveParticular->id,
        'article_id' => $article->id,
        'price' => 500.00,
    ]);

    $channelList = priceListForChannel(PriceListChannel::Mostrador, $article, price: 200.00);
    priceListForChannel(PriceListChannel::General, $article, price: 50.00);

    $customer = Customer::factory()->create(['price_list_id' => $inactiveParticular->id]);

    $result = (new ResolveArticlePrice)->execute($article, PriceListChannel::Mostrador, $customer);

    expect($result->price_list_id)->toBe($channelList->id);
});

test('ignora la lista de canal futura y cae a lista general', function (): void {
    $article = Article::factory()->create();
    $futureChannel = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->futura()->create();

    PriceListItem::factory()->create([
        'price_list_id' => $futureChannel->id,
        'article_id' => $article->id,
        'price' => 300.00,
    ]);

    $generalList = priceListForChannel(PriceListChannel::General, $article, price: 75.00);

    $result = (new ResolveArticlePrice)->execute($article, PriceListChannel::Mostrador);

    expect($result->price_list_id)->toBe($generalList->id);
});

// ---------------------------------------------------------------------------
// Result includes traceable reference to the originating list
// ---------------------------------------------------------------------------

test('el resultado registra el id y el scope de la lista de origen', function (): void {
    $article = Article::factory()->create();
    $generalList = priceListForChannel(PriceListChannel::General, $article, price: 99.50);

    $result = (new ResolveArticlePrice)->execute($article, PriceListChannel::Mostrador);

    expect($result->price_list_id)->toBe($generalList->id)
        ->and($result->price_list_name)->toBe($generalList->name)
        ->and($result->price_list_scope)->toBe('canal')
        ->and($result->unit_price)->toBe('99.50');
});
