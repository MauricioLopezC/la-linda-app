<?php

use App\Actions\Ecommerce\GetCustomerCart;
use App\Enums\Catalog\ArticleStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Pricing\PriceListScope;
use App\Models\Catalog\Article;
use App\Models\Catalog\Category;
use App\Models\Catalog\UnitOfMeasure;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;

beforeEach(function () {
    $this->onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create(['name' => 'Lista Online Principal']);

    $this->category = Category::factory()->create(['name' => 'Bebidas', 'is_active' => true]);
    $this->unit = UnitOfMeasure::factory()->create([
        'name' => 'Unidad',
        'abbreviation' => 'u',
        'allows_decimal_quantity' => false,
        'is_active' => true,
    ]);

    $this->customer = Customer::factory()->create();

    $this->action = app(GetCustomerCart::class);
});

test('retrieves customer cart with dynamically resolved prices and calculated totals', function () {
    $article1 = Article::factory()->create([
        'description' => 'Agua Mineral 1.5L',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $article1->id,
        'price' => '100.00',
    ]);

    $article2 = Article::factory()->create([
        'description' => 'Gaseosa Cola 2.25L',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $article2->id,
        'price' => '250.00',
    ]);

    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $article1->id,
        'quantity' => '2.000',
    ]);
    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $article2->id,
        'quantity' => '3.000',
    ]);

    $cartData = $this->action->execute($this->customer);

    expect($cartData->lines_count)->toBe(2)
        ->and($cartData->has_unavailable_items)->toBeFalse()
        ->and($cartData->total)->toBe('950.00') // 2 * 100 + 3 * 250 = 200 + 750 = 950
        ->and($cartData->items)->toHaveCount(2);

    expect($cartData->items[0]->unit_price)->toBe('100.00')
        ->and($cartData->items[0]->subtotal)->toBe('200.00')
        ->and($cartData->items[0]->is_available)->toBeTrue();

    expect($cartData->items[1]->unit_price)->toBe('250.00')
        ->and($cartData->items[1]->subtotal)->toBe('750.00')
        ->and($cartData->items[1]->is_available)->toBeTrue();
});

test('dynamically updates prices when price list item changes without editing cart_items table', function () {
    $article = Article::factory()->create([
        'description' => 'Aceite de Girasol 900ml',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    $priceItem = PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $article->id,
        'price' => '500.00',
    ]);

    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $article->id,
        'quantity' => '2.000',
    ]);

    // First check: price is 500.00, subtotal is 1000.00
    $firstCart = $this->action->execute($this->customer);
    expect($firstCart->items[0]->unit_price)->toBe('500.00')
        ->and($firstCart->total)->toBe('1000.00');

    // Update price in price list
    $priceItem->update(['price' => '650.00']);

    // Second check: dynamic resolution returns 650.00 and total 1300.00
    $secondCart = $this->action->execute($this->customer);
    expect($secondCart->items[0]->unit_price)->toBe('650.00')
        ->and($secondCart->total)->toBe('1300.00');
});

test('marks article as unavailable and excludes it from total when article is inactive', function () {
    $activeArticle = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $activeArticle->id,
        'price' => '200.00',
    ]);

    $inactiveArticle = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'status' => ArticleStatus::Inactive,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $inactiveArticle->id,
        'price' => '300.00',
    ]);

    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $activeArticle->id,
        'quantity' => '1.000',
    ]);
    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $inactiveArticle->id,
        'quantity' => '2.000',
    ]);

    $cartData = $this->action->execute($this->customer);

    expect($cartData->has_unavailable_items)->toBeTrue()
        ->and($cartData->total)->toBe('200.00'); // only active article sums up

    $inactiveItem = collect($cartData->items)->firstWhere('article_id', $inactiveArticle->id);
    expect($inactiveItem->is_available)->toBeFalse()
        ->and($inactiveItem->unavailable_reason)->toBe('Artículo inactivo')
        ->and($inactiveItem->subtotal)->toBe('0.00');
});

test('marks article as unavailable and excludes it from total when article is not online publishable', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => false,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $article->id,
        'price' => '150.00',
    ]);

    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $article->id,
        'quantity' => '1.000',
    ]);

    $cartData = $this->action->execute($this->customer);

    expect($cartData->has_unavailable_items)->toBeTrue()
        ->and($cartData->total)->toBe('0.00')
        ->and($cartData->items[0]->is_available)->toBeFalse()
        ->and($cartData->items[0]->unavailable_reason)->toBe('No disponible para venta online');
});

test('marks article as unavailable and excludes it from total when article has no active price', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    // No price item created

    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $article->id,
        'quantity' => '1.000',
    ]);

    $cartData = $this->action->execute($this->customer);

    expect($cartData->has_unavailable_items)->toBeTrue()
        ->and($cartData->total)->toBe('0.00')
        ->and($cartData->items[0]->is_available)->toBeFalse()
        ->and($cartData->items[0]->unavailable_reason)->toBe('Sin precio vigente');
});

test('resolves customer particular price list when assigned', function () {
    $particularList = PriceList::factory()
        ->create([
            'name' => 'Lista VIP Cliente',
            'scope' => PriceListScope::Particular,
        ]);

    $this->customer->update(['price_list_id' => $particularList->id]);

    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    // Channel price: 500
    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $article->id,
        'price' => '500.00',
    ]);

    // Particular price: 420
    PriceListItem::factory()->create([
        'price_list_id' => $particularList->id,
        'article_id' => $article->id,
        'price' => '420.00',
    ]);

    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $article->id,
        'quantity' => '2.000',
    ]);

    $cartData = $this->action->execute($this->customer);

    expect($cartData->items[0]->unit_price)->toBe('420.00')
        ->and($cartData->total)->toBe('840.00');
});
