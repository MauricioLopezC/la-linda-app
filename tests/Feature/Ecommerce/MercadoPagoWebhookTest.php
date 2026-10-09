<?php

use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Ecommerce\WebOrder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.mercadopago.access_token' => 'TEST-ACCESS-TOKEN-WEBHOOK',
        'services.mercadopago.base_url' => 'https://api.mercadopago.test',
    ]);
});

test('approves payment when webhook notification arrives and api confirms approved payment', function () {
    $order = WebOrder::factory()->create([
        'items_amount' => '4000.00',
        'shipping_cost' => '0.00',
        'total_amount' => '4000.00',
        'status' => WebOrderStatus::Pending,
    ]);

    Http::fake([
        'https://api.mercadopago.test/v1/payments/998877' => Http::response([
            'id' => 998877,
            'status' => 'approved',
            'external_reference' => (string) $order->id,
            'transaction_amount' => 4000.0,
            'date_approved' => '2026-10-08T18:00:00.000Z',
        ], 200),
    ]);

    $response = $this->postJson(route('webhooks.mercadopago'), [
        'type' => 'payment',
        'data' => [
            'id' => '998877',
        ],
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
            'order_status' => 'pagado',
        ]);

    $freshOrder = $order->fresh();
    expect($freshOrder->status)->toBe(WebOrderStatus::Paid)
        ->and($freshOrder->mp_payment_id)->toBe('998877')
        ->and($freshOrder->paid_amount)->toBe('4000.00')
        ->and($freshOrder->paid_at)->not->toBeNull();
});

test('does not mark order as paid when payment status is rejected or pending', function () {
    $order = WebOrder::factory()->create([
        'items_amount' => '4000.00',
        'shipping_cost' => '0.00',
        'total_amount' => '4000.00',
        'status' => WebOrderStatus::Pending,
    ]);

    Http::fake([
        'https://api.mercadopago.test/v1/payments/112233' => Http::response([
            'id' => 112233,
            'status' => 'rejected',
            'external_reference' => (string) $order->id,
            'transaction_amount' => 4000.0,
        ], 200),
    ]);

    $response = $this->postJson(route('webhooks.mercadopago'), [
        'type' => 'payment',
        'data' => ['id' => '112233'],
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'acknowledged',
            'payment_status' => 'rejected',
            'order_status' => 'pendiente',
        ]);

    expect($order->fresh()->status)->toBe(WebOrderStatus::Pending)
        ->and($order->fresh()->paid_at)->toBeNull();
});

test('handles duplicate notification idempotently without errors', function () {
    $order = WebOrder::factory()->create([
        'items_amount' => '4000.00',
        'shipping_cost' => '0.00',
        'total_amount' => '4000.00',
        'status' => WebOrderStatus::Pending,
    ]);

    Http::fake([
        'https://api.mercadopago.test/v1/payments/555444' => Http::response([
            'id' => 555444,
            'status' => 'approved',
            'external_reference' => (string) $order->id,
            'transaction_amount' => 4000.0,
            'date_approved' => '2026-10-08T18:00:00.000Z',
        ], 200),
    ]);

    // First arrival
    $this->postJson(route('webhooks.mercadopago'), [
        'type' => 'payment',
        'data' => ['id' => '555444'],
    ])->assertOk();

    // Second duplicate arrival
    $response = $this->postJson(route('webhooks.mercadopago'), [
        'type' => 'payment',
        'data' => ['id' => '555444'],
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
            'order_status' => 'pagado',
        ]);

    expect($order->fresh()->status)->toBe(WebOrderStatus::Paid)
        ->and($order->fresh()->mp_payment_id)->toBe('555444');
});

test('rejects payment when transaction amount does not match order total', function () {
    $order = WebOrder::factory()->create([
        'items_amount' => '4000.00',
        'shipping_cost' => '0.00',
        'total_amount' => '4000.00',
        'status' => WebOrderStatus::Pending,
    ]);

    Http::fake([
        'https://api.mercadopago.test/v1/payments/777888' => Http::response([
            'id' => 777888,
            'status' => 'approved',
            'external_reference' => (string) $order->id,
            'transaction_amount' => 3000.0, // Different amount
            'date_approved' => '2026-10-08T18:00:00.000Z',
        ], 200),
    ]);

    $response = $this->postJson(route('webhooks.mercadopago'), [
        'type' => 'payment',
        'data' => ['id' => '777888'],
    ]);

    $response->assertStatus(422);
    expect($order->fresh()->status)->toBe(WebOrderStatus::Pending);
});

test('ignores non-payment notifications gracefully', function () {
    $response = $this->postJson(route('webhooks.mercadopago'), [
        'type' => 'merchant_order',
        'data' => ['id' => '123'],
    ]);

    $response->assertOk()
        ->assertJson(['status' => 'ignored']);
});
