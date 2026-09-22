<?php

use App\Enums\Pricing\PriceListChannel;
use App\Models\Catalog\Article;
use App\Models\Catalog\Category;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

test('guest cannot access the price loading screen', function () {
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();

    $this->get(route('pricing.price-lists.show', $priceList))->assertRedirect(route('login'));
});

test('user sees every active article of the catalog with the price it holds in the list', function () {
    $user = User::factory()->create();
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $priced = Article::factory()->create(['description' => 'Artículo Con Precio']);
    Article::factory()->create(['description' => 'Artículo Sin Precio']);

    PriceListItem::factory()->create([
        'price_list_id' => $priceList->id,
        'article_id' => $priced->id,
        'price' => 180.50,
    ]);

    $this->actingAs($user)->get(route('pricing.price-lists.show', $priceList))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pricing/price-lists/show')
            ->where('priceList.id', $priceList->id)
            ->has('articles.data', 2)
            ->where('articles.data.0.description', 'Artículo Con Precio')
            ->where('articles.data.0.price', '180.50')
            ->where('articles.data.0.has_price', true)
            ->where('articles.data.1.description', 'Artículo Sin Precio')
            ->where('articles.data.1.price', null)
            ->where('articles.data.1.has_price', false)
        );
});

test('user can set and then correct the price of an article', function () {
    $user = User::factory()->create();
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $article = Article::factory()->create();

    $this->actingAs($user)->post(route('pricing.price-lists.items.store', $priceList), [
        'prices' => [['article_id' => $article->id, 'price' => '180.50']],
    ])->assertSessionHasNoErrors();

    expect(PriceListItem::query()->count())->toBe(1);
    expect(PriceListItem::firstOrFail()->price)->toBe('180.50');

    $this->actingAs($user)->post(route('pricing.price-lists.items.store', $priceList), [
        'prices' => [['article_id' => $article->id, 'price' => '199.99']],
    ])->assertSessionHasNoErrors();

    expect(PriceListItem::query()->count())->toBe(1);
    expect(PriceListItem::firstOrFail()->price)->toBe('199.99');
});

test('user can set the price of several articles in a single submission', function () {
    $user = User::factory()->create();
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $articles = Article::factory()->count(3)->create();

    $this->actingAs($user)->post(route('pricing.price-lists.items.store', $priceList), [
        'prices' => $articles->map(fn (Article $article, int $index): array => [
            'article_id' => $article->id,
            'price' => 100 + $index,
        ])->all(),
    ])->assertSessionHasNoErrors();

    expect($priceList->items()->count())->toBe(3);
});

test('the same article cannot be sent twice in the same submission', function () {
    $user = User::factory()->create();
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $article = Article::factory()->create();

    $this->actingAs($user)->post(route('pricing.price-lists.items.store', $priceList), [
        'prices' => [
            ['article_id' => $article->id, 'price' => '100.00'],
            ['article_id' => $article->id, 'price' => '200.00'],
        ],
    ])->assertSessionHasErrors(['prices.1.article_id']);

    expect(PriceListItem::query()->count())->toBe(0);
});

test('database index protects one price per article and list', function () {
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $article = Article::factory()->create();

    PriceListItem::factory()->create([
        'price_list_id' => $priceList->id,
        'article_id' => $article->id,
    ]);

    expect(fn () => PriceListItem::factory()->create([
        'price_list_id' => $priceList->id,
        'article_id' => $article->id,
    ]))->toThrow(QueryException::class);
});

test('the price must be greater than zero', function (string $price) {
    $user = User::factory()->create();
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $article = Article::factory()->create();

    $this->actingAs($user)->post(route('pricing.price-lists.items.store', $priceList), [
        'prices' => [['article_id' => $article->id, 'price' => $price]],
    ])->assertSessionHasErrors(['prices.0.price']);

    expect(PriceListItem::query()->count())->toBe(0);
})->with(['0', '0.00', '-15.00']);

test('the price admits at most two decimals', function () {
    $user = User::factory()->create();
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $article = Article::factory()->create();

    $this->actingAs($user)->post(route('pricing.price-lists.items.store', $priceList), [
        'prices' => [['article_id' => $article->id, 'price' => '180.555']],
    ])->assertSessionHasErrors(['prices.0.price']);

    expect(PriceListItem::query()->count())->toBe(0);
});

test('database check constraint rejects a price of zero or less', function () {
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $article = Article::factory()->create();

    expect(fn () => PriceListItem::factory()->create([
        'price_list_id' => $priceList->id,
        'article_id' => $article->id,
        'price' => 0,
    ]))->toThrow(QueryException::class);
});

test('an inactive article cannot be priced', function () {
    $user = User::factory()->create();
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $article = Article::factory()->inactive()->create();

    $this->actingAs($user)->post(route('pricing.price-lists.items.store', $priceList), [
        'prices' => [['article_id' => $article->id, 'price' => '180.00']],
    ])->assertSessionHasErrors(['prices.0.article_id']);

    expect(PriceListItem::query()->count())->toBe(0);
});

test('an article deactivated after being priced stays visible so its stale price can be removed', function () {
    $user = User::factory()->create();
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $article = Article::factory()->create();

    PriceListItem::factory()->create([
        'price_list_id' => $priceList->id,
        'article_id' => $article->id,
        'price' => 180,
    ]);

    $article->update(['status' => 'inactive']);

    $this->actingAs($user)->get(route('pricing.price-lists.show', $priceList))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('articles.data', 1)
            ->where('articles.data.0.id', $article->id)
            ->where('articles.data.0.is_active', false)
            ->where('articles.data.0.has_price', true)
        );

    $this->actingAs($user)->delete(route('pricing.price-lists.items.destroy', [$priceList, $article]))
        ->assertSessionHasNoErrors();

    expect(PriceListItem::query()->count())->toBe(0);
});

test('an inactive article without a price is left out of the screen', function () {
    $user = User::factory()->create();
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    Article::factory()->inactive()->create();

    $this->actingAs($user)->get(route('pricing.price-lists.show', $priceList))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('articles.data', 0));
});

test('removing a price sends the article back to the without price filter', function () {
    $user = User::factory()->create();
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $article = Article::factory()->create();

    PriceListItem::factory()->create([
        'price_list_id' => $priceList->id,
        'article_id' => $article->id,
    ]);

    $this->actingAs($user)->delete(route('pricing.price-lists.items.destroy', [$priceList, $article]))
        ->assertSessionHasNoErrors();

    $this->actingAs($user)->get(route('pricing.price-lists.show', [$priceList, 'price_status' => 'without_price']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('articles.data', 1)
            ->where('articles.data.0.id', $article->id)
        );
});

test('removing a price only touches the list it was removed from', function () {
    $user = User::factory()->create();
    $counter = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $online = PriceList::factory()->forChannel(PriceListChannel::Online)->create();
    $article = Article::factory()->create();

    PriceListItem::factory()->create(['price_list_id' => $counter->id, 'article_id' => $article->id]);
    PriceListItem::factory()->create(['price_list_id' => $online->id, 'article_id' => $article->id]);

    $this->actingAs($user)->delete(route('pricing.price-lists.items.destroy', [$counter, $article]))
        ->assertSessionHasNoErrors();

    expect($counter->items()->count())->toBe(0);
    expect($online->items()->count())->toBe(1);
});

/**
 * The acceptance criterion of HU-012: the same article priced differently per list must coexist.
 */
test('the same article holds different prices in the counter and online lists', function () {
    $user = User::factory()->create();
    $counter = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create(['name' => 'Lista Mostrador']);
    $online = PriceList::factory()->forChannel(PriceListChannel::Online)->create(['name' => 'Lista Online']);
    $article = Article::factory()->create();

    $this->actingAs($user)->post(route('pricing.price-lists.items.store', $counter), [
        'prices' => [['article_id' => $article->id, 'price' => '180.00']],
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->post(route('pricing.price-lists.items.store', $online), [
        'prices' => [['article_id' => $article->id, 'price' => '210.00']],
    ])->assertSessionHasNoErrors();

    expect($counter->items()->firstOrFail()->price)->toBe('180.00');
    expect($online->items()->firstOrFail()->price)->toBe('210.00');
    expect($article->priceListItems()->count())->toBe(2);
});

test('prices can be loaded into an expired list', function () {
    $user = User::factory()->create();
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->vencida()->create();
    $article = Article::factory()->create();

    $this->actingAs($user)->post(route('pricing.price-lists.items.store', $priceList), [
        'prices' => [['article_id' => $article->id, 'price' => '180.00']],
    ])->assertSessionHasNoErrors();

    expect($priceList->items()->count())->toBe(1);
});

test('prices can be loaded into a future list so it is ready when it takes over', function () {
    $user = User::factory()->create();
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->futura()->create();
    $article = Article::factory()->create();

    $this->actingAs($user)->post(route('pricing.price-lists.items.store', $priceList), [
        'prices' => [['article_id' => $article->id, 'price' => '180.00']],
    ])->assertSessionHasNoErrors();

    expect($priceList->items()->count())->toBe(1);
});

test('articles can be filtered by search term, category and price status', function () {
    $user = User::factory()->create();
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    $beverages = Category::factory()->create(['name' => 'Bebidas']);
    $groceries = Category::factory()->create(['name' => 'Almacén']);

    $cola = Article::factory()->create([
        'description' => 'Gaseosa Sabor Cola Clásica Botella 1.5 L',
        'internal_code' => 'ART-0001',
        'category_id' => $beverages->id,
    ]);
    Article::factory()->create([
        'description' => 'Harina de Trigo 000 Ultrarefinada Paquete 1 kg',
        'internal_code' => 'ART-0002',
        'category_id' => $groceries->id,
    ]);

    PriceListItem::factory()->create(['price_list_id' => $priceList->id, 'article_id' => $cola->id]);

    $this->actingAs($user)->get(route('pricing.price-lists.show', [$priceList, 'search' => 'gaseosa']))
        ->assertInertia(fn (Assert $page) => $page->has('articles.data', 1)->where('articles.data.0.id', $cola->id));

    $this->actingAs($user)->get(route('pricing.price-lists.show', [$priceList, 'search' => 'ART-0002']))
        ->assertInertia(fn (Assert $page) => $page->has('articles.data', 1));

    $this->actingAs($user)->get(route('pricing.price-lists.show', [$priceList, 'category_id' => $beverages->id]))
        ->assertInertia(fn (Assert $page) => $page->has('articles.data', 1)->where('articles.data.0.id', $cola->id));

    $this->actingAs($user)->get(route('pricing.price-lists.show', [$priceList, 'price_status' => 'with_price']))
        ->assertInertia(fn (Assert $page) => $page->has('articles.data', 1)->where('articles.data.0.id', $cola->id));

    $this->actingAs($user)->get(route('pricing.price-lists.show', [$priceList, 'price_status' => 'without_price']))
        ->assertInertia(fn (Assert $page) => $page->has('articles.data', 1)->where('articles.data.0.has_price', false));
});

test('the price list listing counts the articles that already have a price', function () {
    $user = User::factory()->create();
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create(['name' => 'Lista A']);
    PriceList::factory()->forChannel(PriceListChannel::Online)->create(['name' => 'Lista B']);

    PriceListItem::factory()->count(3)->create(['price_list_id' => $priceList->id]);

    $this->actingAs($user)->get(route('pricing.price-lists.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('priceLists', 2)
            ->where('priceLists.0.articles_with_price_count', 3)
            ->where('priceLists.1.articles_with_price_count', 0)
        );
});

test('deleting a price list takes its prices with it', function () {
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Mostrador)->create();
    PriceListItem::factory()->count(2)->create(['price_list_id' => $priceList->id]);

    $priceList->delete();

    expect(PriceListItem::query()->count())->toBe(0);
});
