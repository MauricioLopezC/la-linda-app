<?php

use App\Enums\Catalog\ArticleStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Security\UserRole;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Organization\Branch;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->onlineList = PriceList::factory()->forChannel(PriceListChannel::Online)->create();

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

    $this->branch = Branch::factory()->create(['name' => 'Sucursal Centro', 'is_active' => true]);

    $this->article = Article::factory()->create([
        'description' => 'Arroz Largo Fino 1kg',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $this->article->id,
        'price' => '1500.00',
    ]);
});

test('guests are redirected to login from checkout and orders', function () {
    $this->get(route('tienda.checkout.show'))->assertRedirect(route('login'));
    $this->post(route('tienda.checkout.store'))->assertRedirect(route('login'));
    $this->get(route('tienda.orders.index'))->assertRedirect(route('login'));
    $this->get('/tienda/mis-pedidos/1')->assertRedirect(route('login'));
});

test('users without a customer cannot use checkout or orders', function () {
    $staff = User::factory()->create(['role' => UserRole::PersonalInterno, 'customer_id' => null]);

    $this->actingAs($staff)->get(route('tienda.checkout.show'))->assertForbidden();
    $this->actingAs($staff)->post(route('tienda.checkout.store'), ['pickup_branch_id' => $this->branch->id])->assertForbidden();
    $this->actingAs($staff)->get(route('tienda.orders.index'))->assertForbidden();
});

test('checkout shows the cart summary, active branches and shipping cost', function () {
    $this->customer->update(['address' => 'Calle Mitre 123']);

    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $this->article->id,
        'quantity' => '2.000',
    ]);
    Branch::factory()->create(['name' => 'Sucursal Cerrada', 'is_active' => false]);

    $this->actingAs($this->clientUser)
        ->get(route('tienda.checkout.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ecommerce/checkout/show')
            ->where('cart.total', '3000.00')
            ->where('default_shipping_address', 'Calle Mitre 123')
            ->where('shipping_cost', '2500.00')
            ->where('formatted_shipping_cost', '$ 2.500,00')
            ->has('branches', 1)
            ->where('branches.0.name', 'Sucursal Centro'));
});

test('checkout with an empty cart sends the customer back to the cart', function () {
    $this->actingAs($this->clientUser)
        ->get(route('tienda.checkout.show'))
        ->assertRedirect(route('tienda.cart.index'));
});

test('placing the order redirects to its detail and empties the cart', function () {
    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $this->article->id,
        'quantity' => '2.000',
    ]);

    $response = $this->actingAs($this->clientUser)->post(route('tienda.checkout.store'), [
        'delivery_method' => 'retiro',
        'pickup_branch_id' => $this->branch->id,
        'notes' => 'Llamar al llegar',
    ]);

    $order = WebOrder::sole();

    $response->assertRedirect(route('tienda.orders.show', $order))
        ->assertSessionHas('success', "Confirmamos tu pedido N.º {$order->formattedNumber()}.");

    expect($order->customer_id)->toBe($this->customer->id)
        ->and($order->total_amount)->toBe('3000.00')
        ->and($order->shipping_cost)->toBe('0.00')
        ->and($order->notes)->toBe('Llamar al llegar')
        ->and($this->customer->cartItems()->count())->toBe(0);
});

test('placing a delivery order ignores client shipping cost and freezes customer shipping address', function () {
    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $this->article->id,
        'quantity' => '2.000',
    ]);

    $response = $this->actingAs($this->clientUser)->post(route('tienda.checkout.store'), [
        'delivery_method' => 'envio',
        'shipping_address' => 'Av. San Martín 789, Salta',
        'shipping_notes' => 'Depto 4B, timbre blanco',
        'notes' => 'Entrega por la tarde',
        'shipping_cost' => '99999.00', // Client cannot set or tamper shipping cost
    ]);

    $order = WebOrder::sole();

    $response->assertRedirect(route('tienda.orders.show', $order))
        ->assertSessionHas('success', "Confirmamos tu pedido N.º {$order->formattedNumber()}.");

    expect($order->customer_id)->toBe($this->customer->id)
        ->and($order->delivery_method->value)->toBe('envio')
        ->and($order->pickup_branch_id)->toBeNull()
        ->and($order->shipping_address)->toBe('Av. San Martín 789, Salta')
        ->and($order->shipping_notes)->toBe('Depto 4B, timbre blanco')
        ->and($order->items_amount)->toBe('3000.00')
        ->and($order->shipping_cost)->toBe('2500.00')
        ->and($order->total_amount)->toBe('5500.00')
        ->and($order->notes)->toBe('Entrega por la tarde')
        ->and($this->customer->cartItems()->count())->toBe(0);
});

test('placing a delivery order validates shipping address', function () {
    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $this->article->id,
        'quantity' => '1.000',
    ]);

    $this->actingAs($this->clientUser)
        ->from(route('tienda.checkout.show'))
        ->post(route('tienda.checkout.store'), [
            'delivery_method' => 'envio',
            'shipping_address' => '',
        ])
        ->assertRedirect(route('tienda.checkout.show'))
        ->assertSessionHasErrors('shipping_address');

    expect(WebOrder::count())->toBe(0);
});

test('placing the order validates the pickup branch', function (?int $branchId) {
    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $this->article->id,
        'quantity' => '1.000',
    ]);

    $this->actingAs($this->clientUser)
        ->from(route('tienda.checkout.show'))
        ->post(route('tienda.checkout.store'), [
            'delivery_method' => 'retiro',
            'pickup_branch_id' => $branchId ?? Branch::factory()->create(['is_active' => false])->id,
        ])
        ->assertRedirect(route('tienda.checkout.show'))
        ->assertSessionHasErrors('pickup_branch_id');

    expect(WebOrder::count())->toBe(0);
})->with([
    'inactive branch' => [null],
    'missing branch' => [999999],
]);

test('placing the order with an unavailable article shows the error on checkout', function () {
    $this->article->update(['is_online_publishable' => false]);
    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $this->article->id,
        'quantity' => '1.000',
    ]);

    $this->actingAs($this->clientUser)
        ->from(route('tienda.checkout.show'))
        ->post(route('tienda.checkout.store'), ['pickup_branch_id' => $this->branch->id])
        ->assertRedirect(route('tienda.checkout.show'))
        ->assertSessionHasErrors(['cart' => 'No se puede confirmar el pedido porque hay artículos no disponibles: Arroz Largo Fino 1kg (no disponible para venta online). Quitalos del carrito para continuar.']);
});

test('my orders lists only the customer own orders, newest first', function () {
    $older = WebOrder::factory()->create(['customer_id' => $this->customer->id, 'placed_at' => now()->subDay()]);
    $newer = WebOrder::factory()->create(['customer_id' => $this->customer->id, 'placed_at' => now()]);
    WebOrder::factory()->create(['customer_id' => $this->otherCustomer->id]);

    $this->actingAs($this->clientUser)
        ->get(route('tienda.orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ecommerce/orders/index')
            ->has('orders.data', 2)
            ->where('orders.data.0.id', $newer->id)
            ->where('orders.data.1.id', $older->id)
            ->where('orders.data.0.status_label', 'Pendiente de pago'));
});

test('order detail shows the frozen lines to its owner', function () {
    $order = WebOrder::factory()->create([
        'customer_id' => $this->customer->id,
        'pickup_branch_id' => $this->branch->id,
        'items_amount' => '3000.00',
        'total_amount' => '3000.00',
    ]);
    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $this->article->id,
        'quantity' => '2.000',
        'unit_price' => '1500.00',
        'price_list_id' => $this->onlineList->id,
        'line_total' => '3000.00',
    ]);

    $this->actingAs($this->clientUser)
        ->get(route('tienda.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ecommerce/orders/show')
            ->where('order.formatted_number', $order->formattedNumber())
            ->where('order.pickup_branch_name', 'Sucursal Centro')
            ->where('order.total_amount', '3000.00')
            ->has('order.items', 1)
            ->where('order.items.0.article_description', 'Arroz Largo Fino 1kg')
            ->where('order.items.0.price_list_name', $this->onlineList->name));
});

test('order detail shows delivery address and shipping indications to its owner', function () {
    $order = WebOrder::factory()->shipping('2500.00')->create([
        'customer_id' => $this->customer->id,
        'shipping_address' => 'Av. San Martín 789, Salta',
        'shipping_notes' => 'Depto 4B',
        'items_amount' => '3000.00',
        'total_amount' => '5500.00',
    ]);
    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $this->article->id,
        'quantity' => '2.000',
        'unit_price' => '1500.00',
        'price_list_id' => $this->onlineList->id,
        'line_total' => '3000.00',
    ]);

    $this->actingAs($this->clientUser)
        ->get(route('tienda.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ecommerce/orders/show')
            ->where('order.formatted_number', $order->formattedNumber())
            ->where('order.delivery_method', 'envio')
            ->where('order.shipping_address', 'Av. San Martín 789, Salta')
            ->where('order.shipping_notes', 'Depto 4B')
            ->where('order.shipping_cost', '2500.00')
            ->where('order.total_amount', '5500.00'));
});

test('a customer cannot see another customer order', function () {
    $foreignOrder = WebOrder::factory()->create(['customer_id' => $this->otherCustomer->id]);

    $this->actingAs($this->clientUser)
        ->get(route('tienda.orders.show', $foreignOrder))
        ->assertForbidden();
});
