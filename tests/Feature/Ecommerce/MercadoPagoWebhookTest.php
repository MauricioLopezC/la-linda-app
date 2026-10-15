<?php

use App\Actions\Ecommerce\DeliverWebOrder;
use App\Actions\Ecommerce\StartWebOrderPreparation;
use App\Enums\Catalog\ArticleStatus;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Catalog\Article;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\VatRate;
use App\Models\Sales\CashMovement;
use App\Models\Sales\Invoice;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config([
        'services.mercadopago.access_token' => 'TEST-ACCESS-TOKEN-WEBHOOK',
        'services.mercadopago.base_url' => 'https://api.mercadopago.test',
    ]);

    $branch = Branch::factory()->create(['is_active' => true]);
    $warehouse = Warehouse::factory()->online()->create([
        'branch_id' => $branch->id,
        'is_active' => true,
    ]);
    $this->onlineWarehouse = $warehouse;
    PointOfSale::factory()->create([
        'warehouse_id' => $warehouse->id,
        'is_active' => true,
    ]);
});

/**
 * Create a pending order with real lines so an approved webhook also exercises invoicing.
 */
function pendingWebhookOrderWithShipping(Warehouse $warehouse): WebOrder
{
    $rate = VatRate::factory()->create(['percentage' => 21]);
    $article = Article::factory()->create(['vat_rate_id' => $rate->id]);
    $list = PriceList::factory()->forChannel(PriceListChannel::Online)->create();
    $order = WebOrder::factory()->shipping('121.00')->create([
        'items_amount' => '242.00',
        'total_amount' => '363.00',
    ]);
    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $article->id,
        'price_list_id' => $list->id,
        'quantity' => '2.000',
        'unit_price' => '121.00',
        'line_total' => '242.00',
    ]);
    StockBalance::factory()->create([
        'article_id' => $article->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '50.000',
    ]);

    return $order->load('items.article');
}

test('approves payment when webhook notification arrives and api confirms approved payment', function () {
    $order = pendingWebhookOrderWithShipping($this->onlineWarehouse);
    $balance = StockBalance::query()->where('article_id', $order->items->first()->article_id)->sole();
    $stockMovements = StockMovement::count();
    $cashMovements = CashMovement::count();

    Http::fake([
        'https://api.mercadopago.test/v1/payments/998877' => Http::response([
            'id' => 998877,
            'status' => 'approved',
            'external_reference' => (string) $order->id,
            'transaction_amount' => 363.0,
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
        ->and($freshOrder->paid_amount)->toBe('363.00')
        ->and($freshOrder->paid_at)->not->toBeNull();

    $sale = $order->fresh()->sale;
    expect($sale)->not->toBeNull()
        ->and($sale->channel)->toBe(SaleChannel::Online)
        ->and($sale->status)->toBe(SaleStatus::Confirmed)
        ->and($sale->point_of_sale_id)->toBe(PointOfSale::query()->where('warehouse_id', $this->onlineWarehouse->id)->sole()->id)
        ->and($sale->cash_session_id)->toBeNull()
        ->and($sale->user_id)->toBeNull()
        ->and($sale->total_amount)->toBe('363.00')
        ->and($sale->shipping_amount)->toBe('121.00')
        ->and($sale->items)->toHaveCount(1)
        ->and($sale->items->first()->quantity)->toBe('2.000')
        ->and($sale->items->first()->unit_price)->toBe('121.00')
        ->and($sale->invoice)->not->toBeNull()
        ->and($sale->invoice->cash_session_id)->toBeNull()
        ->and($sale->invoice->user_id)->toBeNull()
        ->and($sale->invoice->net_amount)->toBe('300.00')
        ->and($sale->invoice->vat_amount)->toBe('63.00')
        ->and($sale->invoice->total_amount)->toBe($order->fresh()->paid_amount)
        ->and($sale->invoice->items)->toHaveCount(2)
        ->and(Sale::query()->where('web_order_id', $order->id)->count())->toBe(1)
        ->and(Invoice::query()->where('sale_id', $sale->id)->count())->toBe(1)
        ->and($balance->fresh()->quantity)->toBe('50.000')
        ->and(StockMovement::count())->toBe($stockMovements)
        ->and(CashMovement::count())->toBe($cashMovements);

    $shipping = $sale->invoice->items->whereNull('article_id')->sole();
    expect($shipping->description)->toBe('Envío a domicilio')
        ->and($shipping->vat_rate)->toBe('21.00')
        ->and($shipping->net_amount)->toBe('100.00')
        ->and($shipping->vat_amount)->toBe('21.00')
        ->and($shipping->line_total)->toBe('121.00');
});

test('stores paid_at in the app timezone when mercado pago reports date_approved with another offset', function () {
    $order = WebOrder::factory()->create([
        'items_amount' => '4000.00',
        'shipping_cost' => '0.00',
        'total_amount' => '4000.00',
        'status' => WebOrderStatus::Pending,
    ]);

    Http::fake([
        'https://api.mercadopago.test/v1/payments/998878' => Http::response([
            'id' => 998878,
            'status' => 'approved',
            'external_reference' => (string) $order->id,
            'transaction_amount' => 4000.0,
            'date_approved' => '2026-10-09T01:11:56.000-04:00',
        ], 200),
    ]);

    $this->postJson(route('webhooks.mercadopago'), [
        'type' => 'payment',
        'data' => [
            'id' => '998878',
        ],
    ])->assertOk();

    expect($order->fresh()->getRawOriginal('paid_at'))->toBe('2026-10-09 02:11:56');
});

test('acknowledges repeated webhooks throughout preparation and delivery without changing stock or documents', function () {
    $order = pendingWebhookOrderWithShipping($this->onlineWarehouse);
    $order->items->first()->update(['quantity' => '5.000', 'unit_price' => '48.40']);
    $balance = StockBalance::query()->where('article_id', $order->items->first()->article_id)->sole();
    $balance->update(['quantity' => '10.000']);
    $operator = User::factory()->create();
    Http::fake([
        'https://api.mercadopago.test/v1/payments/998877' => Http::response([
            'id' => 998877,
            'status' => 'approved',
            'external_reference' => (string) $order->id,
            'transaction_amount' => 363.0,
            'date_approved' => '2026-10-08T18:00:00.000Z',
        ]),
    ]);
    $payload = ['type' => 'payment', 'data' => ['id' => '998877']];
    $this->postJson(route('webhooks.mercadopago'), $payload)->assertOk();
    $sale = $order->fresh()->sale;
    $invoiceId = $sale->invoice->id;
    expect($balance->fresh()->quantity)->toBe('10.000');

    app(StartWebOrderPreparation::class)->execute($order, $operator);
    $preparing = $order->fresh()->getAttributes();
    $this->postJson(route('webhooks.mercadopago'), $payload)
        ->assertOk()->assertJson(['status' => 'ok', 'order_status' => 'en_preparacion']);
    expect($order->fresh()->getAttributes())->toEqual($preparing)
        ->and($balance->fresh()->quantity)->toBe('10.000')
        ->and(StockMovement::where('sale_id', $sale->id)->count())->toBe(0);

    app(DeliverWebOrder::class)->execute($order, $operator);
    $delivered = $order->fresh()->getAttributes();
    $movementId = $sale->fresh()->stockMovement->id;
    $this->postJson(route('webhooks.mercadopago'), $payload)
        ->assertOk()->assertJson(['status' => 'ok', 'order_status' => 'entregado']);
    expect($order->fresh()->getAttributes())->toEqual($delivered)
        ->and($balance->fresh()->quantity)->toBe('5.000')
        ->and($order->fresh()->sale->id)->toBe($sale->id)
        ->and($sale->fresh()->invoice->id)->toBe($invoiceId)
        ->and($sale->fresh()->stockMovement->id)->toBe($movementId)
        ->and(StockMovement::where('sale_id', $sale->id)->count())->toBe(1)
        ->and(Sale::where('web_order_id', $order->id)->count())->toBe(1)
        ->and(Invoice::where('sale_id', $sale->id)->count())->toBe(1);
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
    $order = pendingWebhookOrderWithShipping($this->onlineWarehouse);
    $balance = StockBalance::query()->where('article_id', $order->items->first()->article_id)->sole();
    $stockMovements = StockMovement::count();
    $cashMovements = CashMovement::count();

    Http::fake([
        'https://api.mercadopago.test/v1/payments/555444' => Http::response([
            'id' => 555444,
            'status' => 'approved',
            'external_reference' => (string) $order->id,
            'transaction_amount' => 363.0,
            'date_approved' => '2026-10-08T18:00:00.000Z',
        ], 200),
    ]);

    // First arrival
    $this->postJson(route('webhooks.mercadopago'), [
        'type' => 'payment',
        'data' => ['id' => '555444'],
    ])->assertOk();

    $firstSale = $order->fresh()->sale;
    $firstInvoiceId = $firstSale->invoice->id;

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

    $sale = $order->fresh()->sale;
    expect($sale)->not->toBeNull()
        ->and($sale->channel)->toBe(SaleChannel::Online)
        ->and($sale->status)->toBe(SaleStatus::Confirmed)
        ->and($sale->point_of_sale_id)->toBe(PointOfSale::query()->where('warehouse_id', $this->onlineWarehouse->id)->sole()->id)
        ->and($sale->cash_session_id)->toBeNull()
        ->and($sale->user_id)->toBeNull()
        ->and($sale->total_amount)->toBe('363.00')
        ->and($sale->shipping_amount)->toBe('121.00')
        ->and($sale->items)->toHaveCount(1)
        ->and($sale->items->first()->quantity)->toBe('2.000')
        ->and($sale->items->first()->unit_price)->toBe('121.00')
        ->and($sale->invoice)->not->toBeNull()
        ->and($sale->invoice->cash_session_id)->toBeNull()
        ->and($sale->invoice->user_id)->toBeNull()
        ->and($sale->invoice->net_amount)->toBe('300.00')
        ->and($sale->invoice->vat_amount)->toBe('63.00')
        ->and($sale->invoice->total_amount)->toBe($order->fresh()->paid_amount)
        ->and($sale->invoice->items)->toHaveCount(2)
        ->and(Sale::query()->where('web_order_id', $order->id)->count())->toBe(1)
        ->and(Invoice::query()->where('sale_id', $sale->id)->count())->toBe(1)
        ->and($balance->fresh()->quantity)->toBe('50.000')
        ->and(StockMovement::count())->toBe($stockMovements)
        ->and(CashMovement::count())->toBe($cashMovements);

    $shipping = $sale->invoice->items->whereNull('article_id')->sole();
    expect($shipping->description)->toBe('Envío a domicilio')
        ->and($shipping->vat_rate)->toBe('21.00')
        ->and($shipping->net_amount)->toBe('100.00')
        ->and($shipping->vat_amount)->toBe('21.00')
        ->and($shipping->line_total)->toBe('121.00');

    expect($sale->id)->toBe($firstSale->id)
        ->and($sale->invoice->id)->toBe($firstInvoiceId);
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

test('discards non-existent payment in api with 200 without retrying', function () {
    Http::fake([
        'https://api.mercadopago.test/v1/payments/999999' => Http::response([
            'message' => 'Payment 999999 not found',
            'error' => 'not_found',
            'status' => 404,
        ], 404),
    ]);

    $response = $this->postJson(route('webhooks.mercadopago'), [
        'type' => 'payment',
        'data' => ['id' => '999999'],
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'ignored',
            'reason' => 'payment_not_found',
        ]);

    // 404 is not a transient error: it must NOT retry
    Http::assertSentCount(1);
});

test('retries on transient 500 server error and succeeds when subsequent attempt returns approved', function () {
    $order = WebOrder::factory()->create([
        'items_amount' => '4000.00',
        'shipping_cost' => '0.00',
        'total_amount' => '4000.00',
        'status' => WebOrderStatus::Pending,
    ]);

    Http::fake([
        'https://api.mercadopago.test/v1/payments/887766' => Http::sequence()
            ->push(['message' => 'Internal error'], 500)
            ->push([
                'id' => 887766,
                'status' => 'approved',
                'external_reference' => (string) $order->id,
                'transaction_amount' => 4000.0,
                'date_approved' => '2026-10-08T18:00:00.000Z',
            ], 200),
    ]);

    $response = $this->postJson(route('webhooks.mercadopago'), [
        'type' => 'payment',
        'data' => ['id' => '887766'],
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
            'order_status' => 'pagado',
        ]);

    Http::assertSentCount(2);
    expect($order->fresh()->status)->toBe(WebOrderStatus::Paid);
});

test('handles connection failure to mercado pago api returning 500 without crashing', function () {
    $order = WebOrder::factory()->create([
        'items_amount' => '4000.00',
        'shipping_cost' => '0.00',
        'total_amount' => '4000.00',
        'status' => WebOrderStatus::Pending,
    ]);

    Http::fake([
        'https://api.mercadopago.test/v1/payments/*' => fn () => throw new ConnectionException('Connection refused by host'),
    ]);

    $response = $this->postJson(route('webhooks.mercadopago'), [
        'type' => 'payment',
        'data' => ['id' => '443322'],
    ]);

    $response->assertStatus(500)
        ->assertJson(['error' => 'mercadopago_api_error']);

    expect($order->fresh()->status)->toBe(WebOrderStatus::Pending);
});

test('rolls back an approved webhook when an article has lost its VAT rate', function () {
    $order = pendingWebhookOrderWithShipping($this->onlineWarehouse);
    $article = $order->items->first()->article;
    $article->update(['status' => ArticleStatus::Inactive, 'vat_rate_id' => null]);
    $balance = StockBalance::query()->where('article_id', $article->id)->sole();
    $stockMovements = StockMovement::count();
    $cashMovements = CashMovement::count();
    Log::spy();
    Http::fake([
        'https://api.mercadopago.test/v1/payments/998879' => Http::response([
            'id' => 998879,
            'status' => 'approved',
            'external_reference' => (string) $order->id,
            'transaction_amount' => 363.0,
            'date_approved' => '2026-10-08T18:00:00.000Z',
        ]),
    ]);

    $payload = ['type' => 'payment', 'data' => ['id' => '998879']];
    $this->postJson(route('webhooks.mercadopago'), $payload)
        ->assertInternalServerError()
        ->assertJson(['error' => 'mercadopago_api_error']);

    expect($order->fresh()->status)->toBe(WebOrderStatus::Pending)
        ->and($order->fresh()->mp_payment_id)->toBeNull()
        ->and($order->fresh()->paid_at)->toBeNull()
        ->and($order->fresh()->paid_amount)->toBeNull()
        ->and(Sale::query()->where('web_order_id', $order->id)->exists())->toBeFalse()
        ->and(SaleItem::count())->toBe(0)
        ->and(Invoice::count())->toBe(0)
        ->and($balance->fresh()->quantity)->toBe('50.000')
        ->and(StockMovement::count())->toBe($stockMovements)
        ->and(CashMovement::count())->toBe($cashMovements);
    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, $article->description))->once();

    $article->update(['vat_rate_id' => VatRate::query()->where('percentage', 21)->sole()->id]);
    $this->postJson(route('webhooks.mercadopago'), $payload)->assertOk();
    $this->postJson(route('webhooks.mercadopago'), $payload)->assertOk();

    expect($order->fresh()->status)->toBe(WebOrderStatus::Paid)
        ->and(Sale::query()->where('web_order_id', $order->id)->count())->toBe(1)
        ->and(Invoice::count())->toBe(1)
        ->and($order->fresh()->sale->invoice->total_amount)->toBe('363.00');
});
