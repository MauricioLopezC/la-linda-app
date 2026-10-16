<?php

use App\Actions\Ecommerce\AddArticleToCart;
use App\Actions\Ecommerce\CreateMercadoPagoPreference;
use App\Actions\Ecommerce\GetOnlineAvailability;
use App\Actions\Ecommerce\PlaceWebOrder;
use App\Enums\Ecommerce\DeliveryMethod;
use App\Enums\Pricing\PriceListChannel;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\WebOrder;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use App\Models\Sales\PointOfSale;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.mercadopago.access_token' => 'test', 'services.mercadopago.base_url' => 'https://mp.test', 'ecommerce.reservation_minutes' => 15]);
    Http::preventStrayRequests();
    $this->withoutDefer();
    $this->travelTo(now()->startOfSecond());
    $this->branch = Branch::factory()->create();
    $this->warehouse = Warehouse::factory()->online()->create(['branch_id' => $this->branch->id]);
    PointOfSale::factory()->create(['warehouse_id' => $this->warehouse->id]);
    $list = PriceList::factory()->forChannel(PriceListChannel::Online)->create();
    $this->article = Article::factory()->create(['is_online_publishable' => true]);
    PriceListItem::factory()->create(['article_id' => $this->article->id, 'price_list_id' => $list->id, 'price' => '1000.00']);
    StockBalance::factory()->create(['article_id' => $this->article->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => '3.000']);

    $this->reservedOrder = function (): WebOrder {
        $customer = Customer::factory()->create();
        app(AddArticleToCart::class)->execute($customer, $this->article, '2.000');
        $order = app(PlaceWebOrder::class)->execute($customer, DeliveryMethod::Pickup, $this->branch);
        Http::fake(['https://mp.test/checkout/preferences' => Http::response(['id' => 'pref-a', 'init_point' => 'https://mp.test/pay'], 201)]);
        app(CreateMercadoPagoPreference::class)->execute($order);

        return $order->fresh();
    };
    $this->fakeClosedCheckouts = function (WebOrder $order): void {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            'https://mp.test/checkout/preferences/search*' => Http::response(['elements' => [['id' => 'pref-a']], 'next_offset' => 1, 'total' => 1]),
            'https://mp.test/checkout/preferences/pref-a' => Http::response(['expires' => true, 'expiration_date_to' => $order->stock_reserved_until->toIso8601String()]),
            'https://mp.test/v1/payments/search*' => Http::response(['results' => [], 'paging' => ['total' => 0]]),
        ]);
    };
    $this->availableQuantity = fn (): string => app(GetOnlineAvailability::class)->forArticle($this->article)->availableQuantity;
});

test('store traffic releases an expired reservation without a scheduler', function () {
    $order = ($this->reservedOrder)();
    $this->travel(16)->minutes();
    ($this->fakeClosedCheckouts)($order);

    expect(($this->availableQuantity)())->toBe('1.000');

    $this->get(route('tienda.home'))->assertOk();

    expect($order->fresh()->stock_reserved_until)->toBeNull()
        ->and(($this->availableQuantity)())->toBe('3.000');
});

test('reconciles at most once per minute', function () {
    $order = ($this->reservedOrder)();
    $this->travel(16)->minutes();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['https://mp.test/*' => Http::response([], 503)]);

    $this->get(route('tienda.home'))->assertOk();
    $this->get(route('tienda.home'))->assertOk();
    Http::assertSentCount(1);

    $this->travel(61)->seconds();
    $this->get(route('tienda.home'))->assertOk();
    Http::assertSentCount(2);
    expect($order->fresh()->stock_reserved_until)->not->toBeNull();
});

test('keeps reservations that are still in force without calling Mercado Pago', function () {
    $order = ($this->reservedOrder)();
    Http::swap(new Factory);
    Http::preventStrayRequests();

    $this->get(route('tienda.home'))->assertOk();

    Http::assertNothingSent();
    expect($order->fresh()->stock_reserved_until)->not->toBeNull();
});

test('does not schedule any task so the demo can hibernate', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())->not->toContain('ecommerce:reconcile-reservations');
});
