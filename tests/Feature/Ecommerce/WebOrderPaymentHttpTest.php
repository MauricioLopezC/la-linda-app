<?php

use App\Enums\Catalog\ArticleStatus;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Security\UserRole;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use App\Models\Ecommerce\CustomerAccount;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Organization\Branch;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'services.mercadopago.access_token' => 'TEST-ACCESS-TOKEN-HTTP',
        'services.mercadopago.base_url' => 'https://api.mercadopago.test',
        'services.mercadopago.sandbox' => true,
    ]);

    $this->onlineList = PriceList::factory()->forChannel(PriceListChannel::Online)->create();

    $this->customer = Customer::factory()->create();
    CustomerAccount::factory()->create([
        'customer_id' => $this->customer->id,
        'email' => 'cliente@lalinda.test',
    ]);
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
        'description' => 'Aceite de Girasol 900ml',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $this->article->id,
        'price' => '2000.00',
    ]);
});

test('redirects to mercado pago sandbox upon checkout order confirmation', function () {
    CartItem::factory()->create([
        'customer_id' => $this->customer->id,
        'article_id' => $this->article->id,
        'quantity' => '1.000',
    ]);

    Http::fake([
        'https://api.mercadopago.test/checkout/preferences' => Http::response([
            'id' => 'pref-sandbox-999',
            'init_point' => 'https://www.mercadopago.com/checkout/v1/redirect?pref_id=pref-sandbox-999',
            'sandbox_init_point' => 'https://sandbox.mercadopago.com/checkout/v1/redirect?pref_id=pref-sandbox-999',
        ], 201),
    ]);

    $response = $this->actingAs($this->clientUser)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('tienda.checkout.store'), [
            'delivery_method' => 'retiro',
            'pickup_branch_id' => $this->branch->id,
        ]);

    $order = WebOrder::sole();
    expect($order->mp_preference_id)->toBe('pref-sandbox-999');

    // Inertia::location on Inertia request returns 409 with X-Inertia-Location header
    $response->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://sandbox.mercadopago.com/checkout/v1/redirect?pref_id=pref-sandbox-999');
});

test('allows customer to retry payment for a pending order', function () {
    $order = WebOrder::factory()->create([
        'customer_id' => $this->customer->id,
        'pickup_branch_id' => $this->branch->id,
        'items_amount' => '2000.00',
        'total_amount' => '2000.00',
    ]);
    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $this->article->id,
        'quantity' => '1.000',
        'unit_price' => '2000.00',
        'price_list_id' => $this->onlineList->id,
        'line_total' => '2000.00',
    ]);

    Http::fake([
        'https://api.mercadopago.test/checkout/preferences' => Http::response([
            'id' => 'pref-retry-888',
            'init_point' => 'https://www.mercadopago.com/checkout/v1/redirect?pref_id=pref-retry-888',
            'sandbox_init_point' => 'https://sandbox.mercadopago.com/checkout/v1/redirect?pref_id=pref-retry-888',
        ], 201),
    ]);

    $response = $this->actingAs($this->clientUser)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('tienda.orders.pay', $order));

    $response->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://sandbox.mercadopago.com/checkout/v1/redirect?pref_id=pref-retry-888');
    expect($order->fresh()->mp_preference_id)->toBe('pref-retry-888');
});

test('prevents other customers from paying someone else order', function () {
    $foreignOrder = WebOrder::factory()->create([
        'customer_id' => $this->otherCustomer->id,
    ]);

    $this->actingAs($this->clientUser)
        ->post(route('tienda.orders.pay', $foreignOrder))
        ->assertForbidden();
});

test('prevents re-paying an already paid order', function () {
    $paidOrder = WebOrder::factory()->paid()->create([
        'customer_id' => $this->customer->id,
    ]);

    $this->actingAs($this->clientUser)
        ->post(route('tienda.orders.pay', $paidOrder))
        ->assertRedirect(route('tienda.orders.show', $paidOrder))
        ->assertSessionHas('info', 'El pedido ya se encuentra pagado.');
});

test('displays return page without modifying order status directly', function () {
    $order = WebOrder::factory()->create([
        'customer_id' => $this->customer->id,
        'pickup_branch_id' => $this->branch->id,
        'items_amount' => '2000.00',
        'total_amount' => '2000.00',
    ]);
    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $this->article->id,
        'quantity' => '1.000',
        'unit_price' => '2000.00',
        'price_list_id' => $this->onlineList->id,
        'line_total' => '2000.00',
    ]);

    $this->actingAs($this->clientUser)
        ->get(route('tienda.orders.payment-return', ['web_order' => $order->id, 'status' => 'approved']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ecommerce/orders/payment-return')
            ->where('status', 'approved')
            ->where('order.id', $order->id)
            ->where('order.status', 'pendiente'));

    // Even if query status is approved, the order MUST remain pendiente until webhook confirmation
    expect($order->fresh()->status)->toBe(WebOrderStatus::Pending);
});
