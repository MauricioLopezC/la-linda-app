<?php

use App\Enums\Ecommerce\DeliveryMethod;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use App\Models\Ecommerce\CustomerAccount;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
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
