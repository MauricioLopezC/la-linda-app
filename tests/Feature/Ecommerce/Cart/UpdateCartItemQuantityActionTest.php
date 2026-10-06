<?php

use App\Actions\Ecommerce\UpdateCartItemQuantity;
use App\Enums\Catalog\ArticleStatus;
use App\Models\Catalog\Article;
use App\Models\Catalog\Category;
use App\Models\Catalog\UnitOfMeasure;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
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
    $this->otherCustomer = Customer::factory()->create();

    $this->action = app(UpdateCartItemQuantity::class);
});

test('updates quantity of an existing cart item', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDiscrete->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    $cartItem = CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $article->id,
        'quantity' => '2.000',
    ]);

    $updated = $this->action->execute($this->customer, $cartItem, 5);

    expect((float) $updated->quantity)->toBe(5.0);
    $this->assertDatabaseHas('cart_items', [
        'id' => $cartItem->id,
        'quantity' => '5.000',
    ]);
});

test('rejects updating cart item belonging to another customer', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDiscrete->id,
    ]);

    $cartItem = CartItem::factory()->create([
        'customer_id' => $this->otherCustomer->id,
        'article_id' => $article->id,
        'quantity' => '2.000',
    ]);

    expect(fn () => $this->action->execute($this->customer, $cartItem, 4))
        ->toThrow(AuthorizationException::class);
});

test('rejects zero or negative quantities when updating', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDiscrete->id,
    ]);

    $cartItem = CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $article->id,
        'quantity' => '2.000',
    ]);

    expect(fn () => $this->action->execute($this->customer, $cartItem, 0))
        ->toThrow(ValidationException::class);

    expect(fn () => $this->action->execute($this->customer, $cartItem, -1))
        ->toThrow(ValidationException::class);
});

test('rejects decimal quantities for discrete article when updating', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDiscrete->id,
    ]);

    $cartItem = CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $article->id,
        'quantity' => '2.000',
    ]);

    expect(fn () => $this->action->execute($this->customer, $cartItem, 2.5))
        ->toThrow(ValidationException::class);
});

test('allows decimal quantities for weighable article when updating', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDecimal->id,
    ]);

    $cartItem = CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $article->id,
        'quantity' => '1.000',
    ]);

    $updated = $this->action->execute($this->customer, $cartItem, 2.35);

    expect((float) $updated->quantity)->toBe(2.35);
    $this->assertDatabaseHas('cart_items', [
        'id' => $cartItem->id,
        'quantity' => '2.350',
    ]);
});
