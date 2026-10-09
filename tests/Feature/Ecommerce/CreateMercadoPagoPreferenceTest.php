<?php

use App\Actions\Ecommerce\CreateMercadoPagoPreference;
use App\Enums\Catalog\ArticleStatus;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Models\Catalog\Article;
use App\Models\Catalog\Category;
use App\Models\Catalog\UnitOfMeasure;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CustomerAccount;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Organization\Branch;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

beforeEach(function () {
    config([
        'services.mercadopago.access_token' => 'TEST-ACCESS-TOKEN-123456',
        'services.mercadopago.base_url' => 'https://api.mercadopago.test',
        'services.mercadopago.sandbox' => true,
    ]);

    $this->onlineList = PriceList::factory()->forChannel(PriceListChannel::Online)->create();
    $this->category = Category::factory()->create(['is_active' => true]);
    $this->unit = UnitOfMeasure::factory()->create(['is_active' => true]);

    $this->customer = Customer::factory()->create(['name' => 'Juan Pérez']);
    CustomerAccount::factory()->create([
        'customer_id' => $this->customer->id,
        'email' => 'juan.perez@example.test',
    ]);

    $this->branch = Branch::factory()->create(['is_active' => true]);

    $this->action = app(CreateMercadoPagoPreference::class);
});

test('generates preference with order items and shipping cost for pending order', function () {
    $water = Article::factory()->create([
        'description' => 'Agua Mineral 1.5L',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $water->id,
        'price' => '500.00',
    ]);

    $order = WebOrder::factory()->shipping('2500.00')->create([
        'customer_id' => $this->customer->id,
        'items_amount' => '1000.00',
        'total_amount' => '3500.00',
        'status' => WebOrderStatus::Pending,
    ]);

    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $water->id,
        'quantity' => '2.000',
        'unit_price' => '500.00',
        'price_list_id' => $this->onlineList->id,
        'line_total' => '1000.00',
    ]);

    Http::fake([
        'https://api.mercadopago.test/checkout/preferences' => Http::response([
            'id' => 'pref-123456789',
            'init_point' => 'https://www.mercadopago.com/checkout/v1/redirect?pref_id=pref-123456789',
            'sandbox_init_point' => 'https://sandbox.mercadopago.com/checkout/v1/redirect?pref_id=pref-123456789',
        ], 201),
    ]);

    $result = $this->action->execute($order);

    expect($result['id'])->toBe('pref-123456789')
        ->and($result['redirect_url'])->toBe('https://sandbox.mercadopago.com/checkout/v1/redirect?pref_id=pref-123456789')
        ->and($order->fresh()->mp_preference_id)->toBe('pref-123456789');

    Http::assertSent(function (Request $request) use ($order) {
        $data = $request->data();

        return $request->url() === 'https://api.mercadopago.test/checkout/preferences'
            && $request->hasHeader('Authorization', 'Bearer TEST-ACCESS-TOKEN-123456')
            && $data['external_reference'] === (string) $order->id
            && $data['payer']['name'] === 'Juan Pérez'
            && $data['payer']['email'] === 'juan.perez@example.test'
            && count($data['items']) === 2
            && $data['items'][0]['title'] === 'Agua Mineral 1.5L'
            && $data['items'][0]['unit_price'] == 500.0
            && $data['items'][1]['id'] === 'shipping'
            && $data['items'][1]['unit_price'] == 2500.0;
    });
});

test('rejects generating preference for an already paid order', function () {
    $paidOrder = WebOrder::factory()->paid()->create([
        'customer_id' => $this->customer->id,
    ]);

    expect(fn () => $this->action->execute($paidOrder))
        ->toThrow(RuntimeException::class, 'Solo se pueden generar preferencias para pedidos pendientes de pago');
});
