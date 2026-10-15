<?php

use App\Enums\Ecommerce\DeliveryMethod;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Security\UserRole;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovementType;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->depositoUser = User::factory()->create(['role' => UserRole::Deposito]);
    $this->gerenteUser = User::factory()->create(['role' => UserRole::Gerente]);
    $this->adminUser = User::factory()->create(['role' => UserRole::Administrador]);
    $this->cajeroUser = User::factory()->create(['role' => UserRole::Cajero]);
    $this->comprasUser = User::factory()->create(['role' => UserRole::Compras]);
    $this->clienteUser = User::factory()->create(['role' => UserRole::Cliente]);

    $this->warehouse = Warehouse::factory()->create([
        'is_online_channel' => true,
        'is_active' => true,
    ]);

    StockMovementType::query()
        ->where('code', StockMovementType::CODE_WEB_ORDER_DELIVERY)
        ->first() ?? StockMovementType::create([
            'name' => 'Salida por Entrega de Pedido Web',
            'name_normalized' => 'salida por entrega de pedido web',
            'code' => StockMovementType::CODE_WEB_ORDER_DELIVERY,
            'sign' => -1,
            'description' => 'Salida de mercadería por entrega o despacho de pedido web',
            'is_system' => true,
            'is_active' => true,
        ]);
});

test('guests are redirected to login from warehouse orders', function () {
    $order = WebOrder::factory()->paid()->create();

    $this->get(route('ecommerce.orders.index'))->assertRedirect(route('login'));
    $this->get(route('ecommerce.orders.show', $order))->assertRedirect(route('login'));
    $this->post(route('ecommerce.orders.start-preparation', $order))->assertRedirect(route('login'));
    $this->post(route('ecommerce.orders.deliver', $order))->assertRedirect(route('login'));
});

test('roles with manage-web-orders can access warehouse orders index', function () {
    $this->actingAs($this->depositoUser)->get(route('ecommerce.orders.index'))->assertOk();
    $this->actingAs($this->gerenteUser)->get(route('ecommerce.orders.index'))->assertOk();
    $this->actingAs($this->adminUser)->get(route('ecommerce.orders.index'))->assertOk();
});

test('roles without manage-web-orders are forbidden from warehouse orders', function () {
    $this->actingAs($this->cajeroUser)->get(route('ecommerce.orders.index'))->assertForbidden();
    $this->actingAs($this->comprasUser)->get(route('ecommerce.orders.index'))->assertForbidden();
    $this->actingAs($this->clienteUser)->get(route('ecommerce.orders.index'))->assertRedirect(route('tienda.home'));
    $this->actingAs($this->clienteUser)->getJson(route('ecommerce.orders.index'))->assertForbidden();
});

test('deposito role homeRoute resolves to ecommerce.orders.index', function () {
    expect(UserRole::Deposito->homeRoute())->toBe('ecommerce.orders.index');
});

test('index renders orders, status counts and excludes pending orders', function () {
    WebOrder::factory()->create(['status' => WebOrderStatus::Pending, 'number' => 101]);
    WebOrder::factory()->create(['status' => WebOrderStatus::Pending, 'number' => 102]);
    WebOrder::factory()->paid()->create(['number' => 103]);
    WebOrder::factory()->paid()->create(['number' => 104]);
    WebOrder::factory()->paid()->create(['number' => 105]);
    WebOrder::factory()->inPreparation()->create(['number' => 106]);
    WebOrder::factory()->inPreparation()->create(['number' => 107]);
    WebOrder::factory()->delivered()->create(['number' => 108]);

    $response = $this->actingAs($this->depositoUser)->get(route('ecommerce.orders.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/warehouse-orders/index')
        ->has('orders.data', 6)
        ->where('counts.paid', 3)
        ->where('counts.in_preparation', 2)
        ->where('counts.delivered', 1)
        ->has('branches')
        ->has('filters')
    );
});

test('index filters by status, delivery method and search', function () {
    $customerA = Customer::factory()->create(['name' => 'Gonzalo Higuaín']);
    $customerB = Customer::factory()->create(['name' => 'Lionel Messi']);

    $orderA = WebOrder::factory()->paid()->create([
        'customer_id' => $customerA->id,
        'delivery_method' => DeliveryMethod::Shipping,
        'shipping_address' => 'Av. San Martín 1234',
    ]);

    $orderB = WebOrder::factory()->inPreparation()->create([
        'customer_id' => $customerB->id,
        'delivery_method' => DeliveryMethod::Pickup,
    ]);

    // Filter status = in_preparacion
    $this->actingAs($this->depositoUser)
        ->get(route('ecommerce.orders.index', ['status' => 'en_preparacion']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $orderB->id)
        );

    // Filter delivery_method = envio
    $this->actingAs($this->depositoUser)
        ->get(route('ecommerce.orders.index', ['delivery_method' => 'envio']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $orderA->id)
        );

    // Filter search by customer name
    $this->actingAs($this->depositoUser)
        ->get(route('ecommerce.orders.index', ['search' => 'Messi']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $orderB->id)
        );
});

test('show renders picking sheet with order details and lines', function () {
    $article = Article::factory()->create(['description' => 'Yerba Mate 1kg']);
    $order = WebOrder::factory()->inPreparation()->create();

    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $article->id,
        'quantity' => '2.000',
    ]);

    $response = $this->actingAs($this->depositoUser)->get(route('ecommerce.orders.show', $order));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/warehouse-orders/show')
        ->where('order.id', $order->id)
        ->where('order.formatted_number', $order->formattedNumber())
        ->where('order.status', WebOrderStatus::InPreparation->value)
        ->has('order.items', 1)
        ->where('order.items.0.article_description', 'Yerba Mate 1kg')
    );
});

test('start preparation endpoint transitions order and redirects with flash', function () {
    $order = WebOrder::factory()->paid()->create();

    $response = $this->actingAs($this->depositoUser)
        ->post(route('ecommerce.orders.start-preparation', $order));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe(WebOrderStatus::InPreparation)
        ->and($order->prepared_by)->toBe($this->depositoUser->id);
});

test('deliver endpoint transitions order, discounts stock and redirects with flash', function () {
    $article = Article::factory()->create();

    StockBalance::factory()->create([
        'article_id' => $article->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => '20.000',
    ]);

    $order = WebOrder::factory()->inPreparation()->shipping()->create();
    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $article->id,
        'quantity' => '5.000',
    ]);

    $response = $this->actingAs($this->depositoUser)
        ->post(route('ecommerce.orders.deliver', $order));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe(WebOrderStatus::Delivered)
        ->and($order->delivered_by)->toBe($this->depositoUser->id)
        ->and($order->statusLabel())->toBe('Despachado');

    $balance = StockBalance::query()
        ->where('article_id', $article->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect((float) $balance->quantity)->toBe(15.0);
});

test('contextual status label renders Despachado for shipping and Entregado for pickup', function () {
    $shippingOrder = WebOrder::factory()->delivered()->shipping()->create(['number' => 201]);
    $pickupOrder = WebOrder::factory()->delivered()->create(['delivery_method' => DeliveryMethod::Pickup, 'number' => 202]);

    expect($shippingOrder->statusLabel())->toBe('Despachado')
        ->and($pickupOrder->statusLabel())->toBe('Entregado');

    $response = $this->actingAs($this->depositoUser)->get(route('ecommerce.orders.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('orders.data.0.status_label', fn ($label) => in_array($label, ['Despachado', 'Entregado'], true))
    );
});
