<?php

use App\Actions\Ecommerce\AddArticleToCart;
use App\Actions\Ecommerce\CreateMercadoPagoPreference;
use App\Actions\Ecommerce\GetOnlineAvailability;
use App\Actions\Ecommerce\MarkWebOrderAsPaid;
use App\Actions\Ecommerce\PlaceWebOrder;
use App\Actions\Ecommerce\ReconcileWebOrderReservation;
use App\Enums\Ecommerce\DeliveryMethod;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\WebOrder;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use App\Models\Sales\Invoice;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['services.mercadopago.access_token' => 'test', 'services.mercadopago.base_url' => 'https://mp.test', 'ecommerce.reservation_minutes' => 15]);
    Http::preventStrayRequests();
    $this->travelTo(now()->startOfSecond());
    $this->branch = Branch::factory()->create();
    $this->warehouse = Warehouse::factory()->online()->create(['branch_id' => $this->branch->id]);
    PointOfSale::factory()->create(['warehouse_id' => $this->warehouse->id]);
    $this->list = PriceList::factory()->forChannel(PriceListChannel::Online)->create();
    $this->article = Article::factory()->create(['is_online_publishable' => true]);
    PriceListItem::factory()->create(['article_id' => $this->article->id, 'price_list_id' => $this->list->id, 'price' => '1000.00']);
    $this->balance = StockBalance::factory()->create(['article_id' => $this->article->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => '3.000']);
    $this->makeOrder = function (): WebOrder {
        $customer = Customer::factory()->create();
        app(AddArticleToCart::class)->execute($customer, $this->article, '2.000');

        return app(PlaceWebOrder::class)->execute($customer, DeliveryMethod::Pickup, $this->branch);
    };
    $this->preference = ['id' => 'pref-a', 'init_point' => 'https://mp.test/pay', 'sandbox_init_point' => 'https://mp.test/sandbox'];
    Http::fake(['https://mp.test/checkout/preferences' => Http::response($this->preference, 201)]);
    $this->expiredOrder = function (): WebOrder {
        $order = ($this->makeOrder)();
        app(CreateMercadoPagoPreference::class)->execute($order);
        $this->travel(16)->minutes();

        return $order->fresh();
    };
    $this->mockReconciliation = function (WebOrder $order, array $payments = [], array $overrides = []): void {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(array_replace([
            'https://mp.test/checkout/preferences/search*' => Http::response(['elements' => [['id' => 'pref-a']], 'next_offset' => 1, 'total' => 1]),
            'https://mp.test/checkout/preferences/pref-a' => Http::response(['expires' => true, 'expiration_date_to' => $order->stock_reserved_until->toIso8601String()]),
            'https://mp.test/v1/payments/search*' => Http::response(['results' => $payments, 'paging' => ['total' => count($payments)]]),
        ], $overrides));
    };
});

test('two previously confirmed pending orders cannot pay the same last units', function () {
    $orderA = ($this->makeOrder)();
    $orderB = ($this->makeOrder)();
    app(CreateMercadoPagoPreference::class)->execute($orderA);

    expect(app(GetOnlineAvailability::class)->forArticle($this->article)->availableQuantity)->toBe('1.000');
    expect(fn () => app(CreateMercadoPagoPreference::class)->execute($orderB))
        ->toThrow(ValidationException::class, 'disponible: 1.000');
    Http::assertSentCount(1);
    expect($orderB->fresh()->stock_reserved_until)->toBeNull();

    app(MarkWebOrderAsPaid::class)->execute($orderA, 'payment-a', '2000.00', now());
    expect(app(GetOnlineAvailability::class)->forArticle($this->article)->availableQuantity)->toBe('1.000')
        ->and($orderA->fresh()->stock_reserved_until)->toBeNull()
        ->and(Sale::count())->toBe(1)->and(Invoice::count())->toBe(1)
        ->and($this->balance->fresh()->quantity)->toBe('3.000');
});

test('uses a configurable expiration and reuses the same checkout on retry', function (int $minutes) {
    config(['ecommerce.reservation_minutes' => $minutes]);
    $order = ($this->makeOrder)();
    $first = app(CreateMercadoPagoPreference::class)->execute($order);
    $reservedUntil = $order->fresh()->stock_reserved_until;
    expect($reservedUntil->equalTo(now()->addMinutes($minutes)))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request['expires'] === true
        && $request['expiration_date_to'] === $reservedUntil->toIso8601String());
    $this->travel(2)->minutes();
    expect(app(CreateMercadoPagoPreference::class)->execute($order))->toBe($first)
        ->and($order->fresh()->stock_reserved_until->equalTo($reservedUntil))->toBeTrue();
    Http::assertSentCount(1);
})->with([15, 7]);

test('retains expired stock until all checkouts are closed and payments checked', function () {
    $order = ($this->expiredOrder)();
    expect(app(GetOnlineAvailability::class)->forArticle($this->article)->availableQuantity)->toBe('1.000');
    ($this->mockReconciliation)($order);
    expect(app(ReconcileWebOrderReservation::class)->execute($order))->toBeTrue()
        ->and($order->fresh()->stock_reserved_until)->toBeNull()
        ->and(app(GetOnlineAvailability::class)->forArticle($this->article)->availableQuantity)->toBe('3.000');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && $request['expires'] === true);
});

test('retains a reservation while payment has an intermediate status', function (string $status) {
    $order = ($this->expiredOrder)();
    ($this->mockReconciliation)($order, [['id' => 'p1', 'status' => $status, 'external_reference' => $order->id.':'.$order->payment_reservation_token]]);
    expect(app(ReconcileWebOrderReservation::class)->execute($order))->toBeFalse()
        ->and($order->fresh()->stock_reserved_until)->not->toBeNull();
})->with(['pending', 'in_process', 'authorized', 'unknown']);

test('reconciles an approved payment whose webhook has not arrived without freeing its stock', function () {
    $order = ($this->expiredOrder)();
    $reference = $order->id.':'.$order->payment_reservation_token;
    ($this->mockReconciliation)($order, [['id' => 'p1', 'status' => 'approved', 'external_reference' => $reference]]);
    Http::fake(['https://mp.test/v1/payments/p1' => Http::response([
        'id' => 'p1', 'status' => 'approved', 'external_reference' => $reference,
        'transaction_amount' => 2000, 'date_approved' => now()->subMinutes(2)->toIso8601String(),
    ])]);
    expect(app(ReconcileWebOrderReservation::class)->execute($order))->toBeFalse()
        ->and($order->fresh()->status)->toBe(WebOrderStatus::Paid)
        ->and(app(GetOnlineAvailability::class)->forArticle($this->article)->availableQuantity)->toBe('1.000');
    app(MarkWebOrderAsPaid::class)->execute($order->fresh(), 'p1', '2000.00', now());
    expect(Sale::count())->toBe(1)->and(Invoice::count())->toBe(1);
});

test('keeps stock reserved when payment verification fails and the command reports failure', function () {
    $order = ($this->expiredOrder)();
    Http::fake(['https://mp.test/checkout/preferences/search*' => Http::response([], 503)]);
    $this->artisan('ecommerce:reconcile-reservations')->assertFailed();
    expect($order->fresh()->stock_reserved_until)->not->toBeNull();
});

test('a timed out preference request keeps stock protected until reconciliation', function () {
    $order = ($this->makeOrder)();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['https://mp.test/checkout/preferences' => Http::failedConnection()]);
    expect(fn () => app(CreateMercadoPagoPreference::class)->execute($order))->toThrow(ConnectionException::class);
    expect($order->fresh()->stock_reserved_until)->not->toBeNull()
        ->and(app(GetOnlineAvailability::class)->forArticle($this->article)->availableQuantity)->toBe('1.000');
    expect(fn () => app(CreateMercadoPagoPreference::class)->execute($order))->toThrow(ValidationException::class, 'ya se está preparando');
});

test('a renewed checkout checks stock again and uses a new attempt reference', function () {
    $order = ($this->expiredOrder)();
    $oldToken = $order->payment_reservation_token;
    ($this->mockReconciliation)($order);
    app(ReconcileWebOrderReservation::class)->execute($order);
    Http::fake(['https://mp.test/checkout/preferences' => Http::response($this->preference, 201)]);
    app(CreateMercadoPagoPreference::class)->execute($order->fresh());
    expect($order->fresh()->payment_reservation_token)->not->toBe($oldToken)
        ->and($order->fresh()->stock_reserved_until->equalTo(now()->addMinutes(15)))->toBeTrue();
});

test('a second request cannot create another checkout while the first request is in flight', function () {
    $order = ($this->makeOrder)();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['https://mp.test/checkout/preferences' => function () use ($order) {
        expect(fn () => app(CreateMercadoPagoPreference::class)->execute($order->fresh()))
            ->toThrow(ValidationException::class, 'ya se está preparando');

        return Http::response($this->preference, 201);
    }]);
    app(CreateMercadoPagoPreference::class)->execute($order);
    Http::assertSentCount(1);
});

test('a renewed payment is refused before contacting Mercado Pago if stock has decreased', function () {
    $order = ($this->expiredOrder)();
    ($this->mockReconciliation)($order);
    app(ReconcileWebOrderReservation::class)->execute($order);
    $this->balance->update(['quantity' => '1.000']);
    Http::fake();
    expect(fn () => app(CreateMercadoPagoPreference::class)->execute($order->fresh()))
        ->toThrow(ValidationException::class, 'disponible: 1.000');
    Http::assertNothingSent();
    expect($order->fresh()->stock_reserved_until)->toBeNull();
});

test('does not release stock when the checkout expiration has not been confirmed', function () {
    $order = ($this->expiredOrder)();
    ($this->mockReconciliation)($order, [], ['https://mp.test/checkout/preferences/pref-a' => Http::response(['expires' => false])]);
    expect(fn () => app(ReconcileWebOrderReservation::class)->execute($order))->toThrow(RuntimeException::class, 'no confirmó');
    expect($order->fresh()->stock_reserved_until)->not->toBeNull();
});

test('checks every payment page before releasing a reservation', function () {
    $order = ($this->expiredOrder)();
    $reference = $order->id.':'.$order->payment_reservation_token;
    ($this->mockReconciliation)($order, [], ['https://mp.test/v1/payments/search*' => fn (Request $request) => Http::response([
        'results' => [['id' => (int) $request['offset'] === 0 ? 'p1' : 'p2', 'status' => (int) $request['offset'] === 0 ? 'rejected' : 'in_process', 'external_reference' => $reference]],
        'paging' => ['total' => 2],
    ])]);
    expect(app(ReconcileWebOrderReservation::class)->execute($order))->toBeFalse()
        ->and($order->fresh()->stock_reserved_until)->not->toBeNull();
});

test('protects legacy pending checkouts during migration and reconciles their original references', function () {
    $order = ($this->makeOrder)();
    $order->update(['mp_preference_id' => 'pref-a']);
    $migration = require database_path('migrations/2026_10_16_120349_reserve_existing_pending_web_order_checkouts.php');
    $migration->up();
    $order->refresh();
    expect($order->stock_reserved_until)->not->toBeNull()
        ->and($order->payment_reservation_token)->toBeNull()
        ->and(app(GetOnlineAvailability::class)->forArticle($this->article)->availableQuantity)->toBe('1.000');
    ($this->mockReconciliation)($order);
    expect(app(ReconcileWebOrderReservation::class)->execute($order))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/v1/payments/search') && $request['external_reference'] === (string) $order->id);
});

test('does not expose a checkout if its creation finishes after the reservation deadline', function () {
    $order = ($this->makeOrder)();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['https://mp.test/checkout/preferences' => function () {
        $this->travel(16)->minutes();

        return Http::response($this->preference, 201);
    }]);
    expect(fn () => app(CreateMercadoPagoPreference::class)->execute($order))->toThrow(RuntimeException::class);
    expect($order->fresh()->mp_preference_id)->toBe('pref-a')
        ->and($order->fresh()->stock_reserved_until)->not->toBeNull();
});

test('releases a reservation after rejected or cancelled payments', function (string $status) {
    $order = ($this->expiredOrder)();
    ($this->mockReconciliation)($order, [['id' => 'p1', 'status' => $status, 'external_reference' => $order->id.':'.$order->payment_reservation_token]]);
    expect(app(ReconcileWebOrderReservation::class)->execute($order))->toBeTrue()
        ->and(app(GetOnlineAvailability::class)->forArticle($this->article)->availableQuantity)->toBe('3.000');
})->with(['rejected', 'cancelled']);

test('recovers a checkout whose creation response was lost and closes it before releasing stock', function () {
    $order = ($this->makeOrder)();
    Http::swap(new Factory);
    Http::fake(['https://mp.test/checkout/preferences' => Http::failedConnection()]);
    expect(fn () => app(CreateMercadoPagoPreference::class)->execute($order))->toThrow(ConnectionException::class);
    $this->travel(16)->minutes();
    $order->refresh();
    expect($order->mp_preference_id)->toBeNull();
    ($this->mockReconciliation)($order);
    expect(app(ReconcileWebOrderReservation::class)->execute($order))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && str_ends_with($request->url(), '/pref-a'));
});
