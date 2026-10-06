<?php

use App\Actions\Ecommerce\ClearCart;
use App\Actions\Ecommerce\RemoveCartItem;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->customer = Customer::factory()->create();
    $this->otherCustomer = Customer::factory()->create();

    $this->removeAction = app(RemoveCartItem::class);
    $this->clearAction = app(ClearCart::class);
});

test('removes an item from the customer cart', function () {
    $cartItem = CartItem::factory()->create([
        'customer_id' => $this->customer->id,
    ]);

    $this->removeAction->execute($this->customer, $cartItem);

    $this->assertDatabaseMissing('cart_items', [
        'id' => $cartItem->id,
    ]);
});

test('rejects removing an item belonging to another customer', function () {
    $cartItem = CartItem::factory()->create([
        'customer_id' => $this->otherCustomer->id,
    ]);

    expect(fn () => $this->removeAction->execute($this->customer, $cartItem))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseHas('cart_items', [
        'id' => $cartItem->id,
    ]);
});

test('clears all items from the customer cart', function () {
    $article1 = Article::factory()->create();
    $article2 = Article::factory()->create();

    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $article1->id,
    ]);
    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $article2->id,
    ]);

    // Another customer's item should remain intact
    $otherItem = CartItem::factory()->create([
        'customer_id' => $this->otherCustomer->id,
    ]);

    $this->clearAction->execute($this->customer);

    expect(CartItem::where('customer_id', $this->customer->id)->count())->toBe(0);
    $this->assertDatabaseHas('cart_items', [
        'id' => $otherItem->id,
    ]);
});
