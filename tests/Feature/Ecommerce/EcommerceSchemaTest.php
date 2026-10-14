<?php

use App\Enums\Ecommerce\DeliveryMethod;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use App\Models\Ecommerce\CustomerAccount;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\Sales\PointOfSale;
use App\Models\User;
use Illuminate\Database\QueryException;

test('a customer has at most one store account', function () {
    $account = CustomerAccount::factory()->create();

    expect($account->customer->account->id)->toBe($account->id)
        ->and(inSavepoint(fn () => CustomerAccount::factory()->create(['customer_id' => $account->customer_id])))
        ->toThrow(QueryException::class);
});

test('store account emails are unique regardless of case and outer spaces', function () {
    $account = CustomerAccount::factory()->create(['email' => '  Cliente@LaLinda.com ']);

    expect($account->email)->toBe('cliente@lalinda.com')
        ->and(inSavepoint(fn () => CustomerAccount::factory()->create(['email' => 'CLIENTE@lalinda.com'])))
        ->toThrow(QueryException::class);
});

test('a store account never exposes its password', function () {
    expect(CustomerAccount::factory()->create()->toArray())->not->toHaveKey('password');
});

test('an article appears once per cart with a positive quantity', function () {
    $item = CartItem::factory()->create();

    expect($item->customer->cartItems()->count())->toBe(1)
        ->and(inSavepoint(fn () => CartItem::factory()->create([
            'customer_id' => $item->customer_id,
            'article_id' => $item->article_id,
        ])))->toThrow(QueryException::class)
        ->and(inSavepoint(fn () => CartItem::factory()->create(['quantity' => 0])))
        ->toThrow(QueryException::class);
});

test('a pickup order needs a branch and a shipping order needs an address', function () {
    $pickup = WebOrder::factory()->create();
    $shipping = WebOrder::factory()->shipping('2500.00')->create();

    expect($pickup->delivery_method)->toBe(DeliveryMethod::Pickup)
        ->and($pickup->pickupBranch)->not->toBeNull()
        ->and($shipping->delivery_method)->toBe(DeliveryMethod::Shipping)
        ->and($shipping->total_amount)->toBe(number_format((float) $shipping->items_amount + 2500, 2, '.', ''));

    expect(inSavepoint(fn () => WebOrder::factory()->create(['pickup_branch_id' => null])))
        ->toThrow(QueryException::class)
        ->and(inSavepoint(fn () => WebOrder::factory()->shipping()->create(['shipping_address' => null])))
        ->toThrow(QueryException::class);
});

test('an order total is its items plus shipping', function () {
    expect(inSavepoint(fn () => WebOrder::factory()->create([
        'items_amount' => '1000.00',
        'shipping_cost' => '0.00',
        'total_amount' => '1500.00',
    ])))->toThrow(QueryException::class);
});

test('an order is paid exactly when it has a payment time', function () {
    $paid = WebOrder::factory()->paid()->create();

    expect($paid->status)->toBe(WebOrderStatus::Paid)
        ->and($paid->paid_amount)->toBe($paid->total_amount);

    expect(inSavepoint(fn () => WebOrder::factory()->create(['paid_at' => now()])))
        ->toThrow(QueryException::class)
        ->and(inSavepoint(fn () => WebOrder::factory()->paid()->create(['paid_at' => null])))
        ->toThrow(QueryException::class);
});

test('order numbers and Mercado Pago payments are unique', function () {
    $order = WebOrder::factory()->paid()->create();

    expect(inSavepoint(fn () => WebOrder::factory()->create(['number' => $order->number])))
        ->toThrow(QueryException::class)
        ->and(inSavepoint(fn () => WebOrder::factory()->paid()->create(['mp_payment_id' => $order->mp_payment_id])))
        ->toThrow(QueryException::class);
});

test('an article appears once per order', function () {
    $item = WebOrderItem::factory()->create();

    expect($item->webOrder->items()->count())->toBe(1)
        ->and(inSavepoint(fn () => WebOrderItem::factory()->create([
            'web_order_id' => $item->web_order_id,
            'article_id' => $item->article_id,
        ])))->toThrow(QueryException::class);
});

test('a customer with online orders has associated records', function () {
    $customer = Customer::factory()->create();

    expect($customer->hasAssociatedRecords())->toBeFalse();

    WebOrder::factory()->create(['customer_id' => $customer->id]);

    expect($customer->hasAssociatedRecords())->toBeTrue();
});

test('an order in preparation or delivered requires preparation audit fields', function () {
    $inPreparation = WebOrder::factory()->inPreparation()->create();

    expect($inPreparation->status)->toBe(WebOrderStatus::InPreparation)
        ->and($inPreparation->paid_at)->not->toBeNull()
        ->and($inPreparation->preparation_started_at)->not->toBeNull()
        ->and($inPreparation->preparedBy)->not->toBeNull()
        ->and($inPreparation->delivered_at)->toBeNull()
        ->and($inPreparation->delivered_by)->toBeNull();

    expect(inSavepoint(fn () => WebOrder::factory()->create(['preparation_started_at' => now()])))
        ->toThrow(QueryException::class)
        ->and(inSavepoint(fn () => WebOrder::factory()->paid()->create(['preparation_started_at' => now()])))
        ->toThrow(QueryException::class)
        ->and(inSavepoint(fn () => WebOrder::factory()->inPreparation()->create(['preparation_started_at' => null])))
        ->toThrow(QueryException::class)
        ->and(inSavepoint(fn () => WebOrder::factory()->inPreparation()->create(['prepared_by' => null])))
        ->toThrow(QueryException::class);
});

test('a delivered order requires delivery audit fields and resolves its status label by delivery method', function () {
    $pickupDelivered = WebOrder::factory()->delivered()->create();
    $shippingDelivered = WebOrder::factory()->shipping('2500.00')->delivered()->create();

    expect($pickupDelivered->status)->toBe(WebOrderStatus::Delivered)
        ->and($pickupDelivered->delivered_at)->not->toBeNull()
        ->and($pickupDelivered->deliveredBy)->not->toBeNull()
        ->and($pickupDelivered->statusLabel())->toBe('Entregado')
        ->and($shippingDelivered->statusLabel())->toBe('Despachado');

    expect(inSavepoint(fn () => WebOrder::factory()->inPreparation()->create(['delivered_at' => now()])))
        ->toThrow(QueryException::class)
        ->and(inSavepoint(fn () => WebOrder::factory()->delivered()->create(['delivered_at' => null])))
        ->toThrow(QueryException::class)
        ->and(inSavepoint(fn () => WebOrder::factory()->delivered()->create(['delivered_by' => null])))
        ->toThrow(QueryException::class);
});

test('web orders preparation migration blocks rollback when orders in preparation or delivered exist', function () {
    $order = WebOrder::factory()->inPreparation()->create();
    $migration = require database_path('migrations/2026_10_12_100002_revise_web_orders_for_preparation_and_delivery.php');

    expect(fn () => $migration->down())->toThrow(
        RuntimeException::class,
        'existen pedidos en preparación o entregados'
    );

    $this->assertDatabaseHas('web_orders', ['id' => $order->id]);
});

test('a user has an active flag defaulting to true and an optional default branch that nulls on delete', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $inactive = User::factory()->create(['is_active' => false]);

    expect($user->is_active)->toBeTrue()
        ->and($user->branch?->id)->toBe($branch->id)
        ->and(User::query()->active()->pluck('id')->all())->toContain($user->id)
        ->and(User::query()->active()->pluck('id')->all())->not->toContain($inactive->id);

    $branch->delete();

    expect($user->fresh()->branch_id)->toBeNull();
});

test('all six sprint 5 schema migrations roll back and re-run cleanly when data is compatible', function () {
    $paidOrder = WebOrder::factory()->paid()->create();

    $migrations = [
        require database_path('migrations/2026_10_12_100001_add_branch_and_active_to_users_table.php'),
        require database_path('migrations/2026_10_12_100002_revise_web_orders_for_preparation_and_delivery.php'),
        require database_path('migrations/2026_10_12_100003_revise_sales_for_online_orders.php'),
        require database_path('migrations/2026_10_12_100004_revise_invoices_for_online_sales.php'),
        require database_path('migrations/2026_10_12_100005_ensure_web_order_delivery_stock_movement_type_exists.php'),
        require database_path('migrations/2026_10_12_100006_ensure_online_point_of_sale_exists.php'),
    ];

    foreach (array_reverse($migrations) as $migration) {
        $migration->down();
    }

    foreach ($migrations as $migration) {
        $migration->up();
    }

    $delivered = WebOrder::factory()->delivered()->create();

    expect($delivered->status)->toBe(WebOrderStatus::Delivered)
        ->and(WebOrder::query()->find($paidOrder->id)?->mp_payment_id)->toBe($paidOrder->mp_payment_id);
});

test('the online point of sale migration gives each active online warehouse the next free number once', function () {
    $counterWarehouse = Warehouse::factory()->create();
    PointOfSale::factory()->create(['number' => 1, 'warehouse_id' => $counterWarehouse->id]);
    PointOfSale::factory()->create(['number' => 2, 'warehouse_id' => $counterWarehouse->id]);
    $onlineWarehouse = Warehouse::factory()->online()->create();
    Warehouse::factory()->online()->create(['is_active' => false]);
    $migration = require database_path('migrations/2026_10_12_100006_ensure_online_point_of_sale_exists.php');

    $migration->up();
    $migration->up();

    expect(PointOfSale::query()->where('warehouse_id', $onlineWarehouse->id)->pluck('number')->all())->toBe([3])
        ->and(PointOfSale::query()->count())->toBe(3);

    $migration->down();

    expect(PointOfSale::query()->where('warehouse_id', $onlineWarehouse->id)->exists())->toBeFalse()
        ->and(PointOfSale::query()->count())->toBe(2);
});
