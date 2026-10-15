<?php

use App\Actions\Ecommerce\DeliverWebOrder;
use App\Enums\Ecommerce\DeliveryMethod;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Security\UserRole;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementType;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
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
    warehouseSaleForHttpOrder($order, $this->warehouse);
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

function warehouseSaleForHttpOrder(WebOrder $order, Warehouse $warehouse): Sale
{
    return Sale::factory()->online()->confirmed()->create([
        'id' => 90001,
        'web_order_id' => $order->id,
        'customer_id' => $order->customer_id,
        'point_of_sale_id' => PointOfSale::factory()->create(['warehouse_id' => $warehouse->id])->id,
    ]);
}

test('searches the associated sale number independently of the order number', function (string $prefix) {
    $order = WebOrder::factory()->paid()->create(['number' => 40001]);
    $sale = warehouseSaleForHttpOrder($order, $this->warehouse);
    WebOrder::factory()->paid()->create(['number' => 40002]);

    $this->actingAs($this->depositoUser)
        ->get(route('ecommerce.orders.index', ['search' => $prefix.$sale->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('orders.data', 1)
        ->where('orders.data.0.id', $order->id));
})->with(['', '#000']);

test('combines sale search with every operational filter', function (string $excludedFilter) {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $order = WebOrder::factory()->inPreparation()->create([
        'number' => 40001,
        'delivery_method' => DeliveryMethod::Pickup,
        'pickup_branch_id' => $branch->id,
        'paid_at' => '2026-10-10 12:00:00',
    ]);
    $sale = warehouseSaleForHttpOrder($order, $this->warehouse);
    WebOrder::factory()->paid()->shipping()->create(['number' => $sale->id]);
    $filters = [
        'search' => (string) $sale->id,
        'status' => 'en_preparacion',
        'delivery_method' => 'retiro',
        'pickup_branch_id' => $branch->id,
        'date_from' => '2026-10-09',
        'date_to' => '2026-10-11',
    ];
    if ($excludedFilter !== 'none') {
        $filters[$excludedFilter] = match ($excludedFilter) {
            'status' => 'entregado',
            'delivery_method' => 'envio',
            'pickup_branch_id' => $otherBranch->id,
            'date_from' => '2026-10-11',
            'date_to' => '2026-10-09',
        };
    }
    $this->actingAs($this->depositoUser)->get(route('ecommerce.orders.index', $filters))
        ->assertOk()->assertInertia(function (Assert $page) use ($excludedFilter, $order): void {
            $page->has('orders.data', $excludedFilter === 'none' ? 1 : 0);
            if ($excludedFilter === 'none') {
                $page->where('orders.data.0.id', $order->id);
            }
        });
})->with(['none', 'status', 'delivery_method', 'pickup_branch_id', 'date_from', 'date_to']);

test('rejects delivery without a sale through HTTP and preserves stock and audit', function () {
    $order = WebOrder::factory()->inPreparation()->create();
    $item = WebOrderItem::factory()->create(['web_order_id' => $order->id]);
    $balance = StockBalance::factory()->create(['article_id' => $item->article_id, 'warehouse_id' => $this->warehouse->id, 'quantity' => '10.000']);
    $snapshot = $order->fresh()->getAttributes();
    $movementCount = StockMovement::count();

    $this->actingAs($this->depositoUser)->post(route('ecommerce.orders.deliver', $order))
        ->assertSessionHasErrors('sale');

    expect($order->fresh()->getAttributes())->toEqual($snapshot)
        ->and($balance->fresh()->quantity)->toBe('10.000')
        ->and(StockMovement::count())->toBe($movementCount);
});

test('shows the operational warehouse before delivery and the historical movement warehouse afterwards', function (string $changedAttribute) {
    $order = WebOrder::factory()->inPreparation()->create();
    $sale = warehouseSaleForHttpOrder($order, $this->warehouse);
    $item = WebOrderItem::factory()->create(['web_order_id' => $order->id, 'quantity' => '2.000']);
    $original = StockBalance::factory()->create(['article_id' => $item->article_id, 'warehouse_id' => $this->warehouse->id, 'quantity' => '10.000']);
    $this->warehouse->update([$changedAttribute => false]);
    $replacement = Warehouse::factory()->online()->create(['is_active' => true]);
    $balance = StockBalance::factory()->create(['article_id' => $item->article_id, 'warehouse_id' => $replacement->id, 'quantity' => '10.000']);

    $this->actingAs($this->depositoUser)->get(route('ecommerce.orders.show', $order))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('order.warehouse_name', $replacement->name)
        ->where('order.warehouse_id', $replacement->id)
        ->where('order.stock_movement', null));

    app(DeliverWebOrder::class)->execute($order, $this->depositoUser);
    $movement = $sale->fresh()->stockMovement;
    $replacement->update(['is_active' => false]);
    Warehouse::factory()->online()->create(['is_active' => true]);

    $this->get(route('ecommerce.orders.show', $order))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('order.warehouse_name', $replacement->name)
        ->where('order.warehouse_id', $replacement->id)
        ->where('order.stock_movement.id', $movement->id)
        ->where('order.stock_movement.type_code', StockMovementType::CODE_WEB_ORDER_DELIVERY)
        ->where('order.stock_movement.type_name', 'Salida por Entrega de Pedido Web')
        ->where('order.stock_movement.sale_id', $sale->id)
        ->where('order.stock_movement.warehouse_id', $replacement->id)
        ->where('order.stock_movement.warehouse_name', $replacement->name));
    expect($balance->fresh()->quantity)->toBe('8.000')
        ->and($original->fresh()->quantity)->toBe('10.000');
})->with(['is_active', 'is_online_channel']);

test('does not invent warehouse or movement data for a historical delivered order', function () {
    $order = WebOrder::factory()->delivered()->create();
    warehouseSaleForHttpOrder($order, $this->warehouse);

    $this->actingAs($this->depositoUser)->get(route('ecommerce.orders.show', $order))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('order.stock_movement', null)
        ->where('order.warehouse_id', null)
        ->where('order.warehouse_name', null));
});
