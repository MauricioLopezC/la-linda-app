<?php

use App\Actions\Ecommerce\AddArticleToCart;
use App\Enums\Catalog\ArticleStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Models\Catalog\Article;
use App\Models\Catalog\Category;
use App\Models\Catalog\UnitOfMeasure;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create(['name' => 'Lista Online Principal']);

    $this->category = Category::factory()->create(['name' => 'Almacén', 'is_active' => true]);
    $this->unitDiscrete = UnitOfMeasure::factory()->create([
        'name' => 'Unidad',
        'abbreviation' => 'u',
        'allows_decimal_quantity' => false,
        'is_active' => true,
    ]);
    $this->unitDecimal = UnitOfMeasure::factory()->create([
        'name' => 'Kilogramo',
        'abbreviation' => 'kg',
        'allows_decimal_quantity' => true,
        'is_active' => true,
    ]);

    $this->customer = Customer::factory()->create();

    $this->action = app(AddArticleToCart::class);
});

test('adds a discrete article to cart successfully', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDiscrete->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $article->id,
        'price' => '250.00',
    ]);

    $cartItem = $this->action->execute($this->customer, $article, 3);

    expect($cartItem)->toBeInstanceOf(CartItem::class)
        ->and($cartItem->customer_id)->toBe($this->customer->id)
        ->and($cartItem->article_id)->toBe($article->id)
        ->and((float) $cartItem->quantity)->toBe(3.0);

    $this->assertDatabaseHas('cart_items', [
        'customer_id' => $this->customer->id,
        'article_id' => $article->id,
        'quantity' => '3.000',
    ]);
});

test('accumulates quantity when adding an article that is already in the cart', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDiscrete->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $article->id,
        'price' => '100.00',
    ]);

    $this->action->execute($this->customer, $article, 2);
    $cartItem = $this->action->execute($this->customer, $article, 3);

    expect((float) $cartItem->quantity)->toBe(5.0);

    expect(CartItem::where('customer_id', $this->customer->id)->where('article_id', $article->id)->count())->toBe(1);

    $this->assertDatabaseHas('cart_items', [
        'customer_id' => $this->customer->id,
        'article_id' => $article->id,
        'quantity' => '5.000',
    ]);
});

test('allows decimal quantities for weighable articles', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDecimal->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $article->id,
        'price' => '500.00',
    ]);

    $cartItem = $this->action->execute($this->customer, $article, 1.75);

    expect((float) $cartItem->quantity)->toBe(1.75);

    $this->assertDatabaseHas('cart_items', [
        'customer_id' => $this->customer->id,
        'article_id' => $article->id,
        'quantity' => '1.750',
    ]);
});

test('rejects decimal quantities for discrete articles', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDiscrete->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $article->id,
        'price' => '300.00',
    ]);

    expect(fn () => $this->action->execute($this->customer, $article, 1.5))
        ->toThrow(ValidationException::class);
});

test('rejects zero or negative quantities', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDiscrete->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $article->id,
        'price' => '300.00',
    ]);

    expect(fn () => $this->action->execute($this->customer, $article, 0))
        ->toThrow(ValidationException::class);

    expect(fn () => $this->action->execute($this->customer, $article, -2))
        ->toThrow(ValidationException::class);
});

test('rejects articles that are not active', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDiscrete->id,
        'status' => ArticleStatus::Inactive,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $article->id,
        'price' => '300.00',
    ]);

    expect(fn () => $this->action->execute($this->customer, $article, 1))
        ->toThrow(ValidationException::class);
});

test('rejects articles that are not online publishable', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDiscrete->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => false,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $article->id,
        'price' => '300.00',
    ]);

    expect(fn () => $this->action->execute($this->customer, $article, 1))
        ->toThrow(ValidationException::class);
});

test('rejects articles without a valid online price in the cascade', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDiscrete->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    // Article has no price in online or general list
    expect(fn () => $this->action->execute($this->customer, $article, 1))
        ->toThrow(ValidationException::class);
});
