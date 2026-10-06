<?php

use App\Enums\Catalog\ArticleStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Security\UserRole;
use App\Models\Catalog\Article;
use App\Models\Catalog\Category;
use App\Models\Catalog\UnitOfMeasure;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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
    $this->clientUser = User::factory()->create([
        'role' => UserRole::Cliente,
        'customer_id' => $this->customer->id,
    ]);

    $this->otherCustomer = Customer::factory()->create();
    $this->otherClientUser = User::factory()->create([
        'role' => UserRole::Cliente,
        'customer_id' => $this->otherCustomer->id,
    ]);
});

test('unauthenticated guest is redirected to login when accessing cart routes', function () {
    $this->get(route('tienda.cart.index'))->assertRedirect(route('login'));
    $this->post(route('tienda.cart.store'), ['article_id' => 1, 'quantity' => 1])->assertRedirect(route('login'));
    $this->patch('/tienda/carrito/1', ['quantity' => 2])->assertRedirect(route('login'));
    $this->delete('/tienda/carrito/1')->assertRedirect(route('login'));
    $this->delete(route('tienda.cart.clear'))->assertRedirect(route('login'));
});

test('authenticated client can view their cart', function () {
    $article = Article::factory()->create([
        'description' => 'Arroz Largo Fino 1kg',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDiscrete->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $article->id,
        'price' => '800.00',
    ]);

    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $article->id,
        'quantity' => '2.000',
    ]);

    $response = $this->actingAs($this->clientUser)->get(route('tienda.cart.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/cart/index')
        ->has('cart.items', 1)
        ->where('cart.items.0.article_id', $article->id)
        ->where('cart.items.0.quantity', '2.000')
        ->where('cart.items.0.unit_price', '800.00')
        ->where('cart.items.0.subtotal', '1600.00')
        ->where('cart.total', '1600.00')
        ->where('cartCount', 1)
    );
});

test('authenticated client can add an article to their cart', function () {
    $article = Article::factory()->create([
        'description' => 'Fideos Guiseros 500g',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDiscrete->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $article->id,
        'price' => '450.00',
    ]);

    $response = $this->actingAs($this->clientUser)->post(route('tienda.cart.store'), [
        'article_id' => $article->id,
        'quantity' => 3,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('cart_items', [
        'customer_id' => $this->customer->id,
        'article_id' => $article->id,
        'quantity' => '3.000',
    ]);
});

test('adding an existing article increments quantity in the database', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDiscrete->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $article->id,
        'price' => '450.00',
    ]);

    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $article->id,
        'quantity' => '2.000',
    ]);

    $response = $this->actingAs($this->clientUser)->post(route('tienda.cart.store'), [
        'article_id' => $article->id,
        'quantity' => 3,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('cart_items', [
        'customer_id' => $this->customer->id,
        'article_id' => $article->id,
        'quantity' => '5.000',
    ]);
});

test('store endpoint validates required fields and business constraints', function () {
    // Missing fields
    $this->actingAs($this->clientUser)->post(route('tienda.cart.store'), [])
        ->assertSessionHasErrors(['article_id', 'quantity']);

    // Non-existent article
    $this->actingAs($this->clientUser)->post(route('tienda.cart.store'), [
        'article_id' => 999999,
        'quantity' => 1,
    ])->assertSessionHasErrors(['article_id']);

    // Negative quantity
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

    $this->actingAs($this->clientUser)->post(route('tienda.cart.store'), [
        'article_id' => $article->id,
        'quantity' => -1,
    ])->assertSessionHasErrors(['quantity']);

    // Discrete unit with decimal quantity
    $this->actingAs($this->clientUser)->post(route('tienda.cart.store'), [
        'article_id' => $article->id,
        'quantity' => 1.5,
    ])->assertSessionHasErrors(['quantity']);
});

test('store endpoint rejects inactive or unpriced articles with validation errors', function () {
    $inactiveArticle = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDiscrete->id,
        'status' => ArticleStatus::Inactive,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $inactiveArticle->id,
        'price' => '100.00',
    ]);

    $this->actingAs($this->clientUser)->post(route('tienda.cart.store'), [
        'article_id' => $inactiveArticle->id,
        'quantity' => 1,
    ])->assertSessionHasErrors(['article_id']);

    $unpricedArticle = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDiscrete->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    $this->actingAs($this->clientUser)->post(route('tienda.cart.store'), [
        'article_id' => $unpricedArticle->id,
        'quantity' => 1,
    ])->assertSessionHasErrors(['article_id']);
});

test('client can update cart item quantity', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDiscrete->id,
    ]);

    $cartItem = CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $article->id,
        'quantity' => '2.000',
    ]);

    $response = $this->actingAs($this->clientUser)->patch(route('tienda.cart.update', $cartItem), [
        'quantity' => 4,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('cart_items', [
        'id' => $cartItem->id,
        'quantity' => '4.000',
    ]);
});

test('client cannot update another clients cart item', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unitDiscrete->id,
    ]);

    $otherCartItem = CartItem::factory()->create([
        'customer_id' => $this->otherCustomer->id,
        'article_id' => $article->id,
        'quantity' => '2.000',
    ]);

    $response = $this->actingAs($this->clientUser)->patch(route('tienda.cart.update', $otherCartItem), [
        'quantity' => 4,
    ]);

    $response->assertForbidden();
});

test('client can delete an item from their cart', function () {
    $article = Article::factory()->create();

    $cartItem = CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $article->id,
    ]);

    $response = $this->actingAs($this->clientUser)->delete(route('tienda.cart.destroy', $cartItem));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('cart_items', [
        'id' => $cartItem->id,
    ]);
});

test('client cannot delete another clients cart item', function () {
    $article = Article::factory()->create();

    $otherCartItem = CartItem::factory()->create([
        'customer_id' => $this->otherCustomer->id,
        'article_id' => $article->id,
    ]);

    $response = $this->actingAs($this->clientUser)->delete(route('tienda.cart.destroy', $otherCartItem));

    $response->assertForbidden();
    $this->assertDatabaseHas('cart_items', [
        'id' => $otherCartItem->id,
    ]);
});

test('client can clear their entire cart', function () {
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

    $otherItem = CartItem::factory()->create([
        'customer_id' => $this->otherCustomer->id,
    ]);

    $response = $this->actingAs($this->clientUser)->delete(route('tienda.cart.clear'));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(CartItem::where('customer_id', $this->customer->id)->count())->toBe(0);
    $this->assertDatabaseHas('cart_items', ['id' => $otherItem->id]);
});

test('cartCount is shared via inertia to reflect cart size dynamically', function () {
    // Guest visiting store has cartCount 0
    $guestResponse = $this->get(route('tienda.home'));
    $guestResponse->assertInertia(fn (Assert $page) => $page
        ->where('cartCount', 0)
    );

    // Client with empty cart has cartCount 0
    $clientResponse = $this->actingAs($this->clientUser)->get(route('tienda.home'));
    $clientResponse->assertInertia(fn (Assert $page) => $page
        ->where('cartCount', 0)
    );

    // Add 2 items to client cart
    $art1 = Article::factory()->create();
    $art2 = Article::factory()->create();

    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $art1->id,
    ]);
    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $art2->id,
    ]);

    $clientWithItemsResponse = $this->actingAs($this->clientUser)->get(route('tienda.home'));
    $clientWithItemsResponse->assertInertia(fn (Assert $page) => $page
        ->where('cartCount', 2)
    );
});
