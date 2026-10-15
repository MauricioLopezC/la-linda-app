<?php

use App\Actions\Ecommerce\MarkWebOrderAsPaid;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Catalog\Article;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Inventory\Warehouse;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\VatRate;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use Carbon\Carbon;
use InvalidArgumentException;
use RuntimeException;

beforeEach(function () {
    $this->action = app(MarkWebOrderAsPaid::class);

    $this->vat21 = VatRate::query()->where('percentage', 21.0)->first()
        ?? VatRate::factory()->create(['percentage' => 21.0, 'description' => 'IVA 21%']);

    $this->onlineWarehouse = Warehouse::factory()->online()->create([
        'name' => 'Depósito E-commerce',
        'is_active' => true,
    ]);

    $this->onlinePos = PointOfSale::factory()->create([
        'warehouse_id' => $this->onlineWarehouse->id,
        'number' => 2,
        'is_active' => true,
    ]);

    $this->onlinePriceList = PriceList::factory()->forChannel(PriceListChannel::Online)->create();

    $this->article = Article::factory()->create([
        'vat_rate_id' => $this->vat21->id,
    ]);
});

test('marks pending order as paid and creates confirmed sale with invoice atomically', function () {
    $order = WebOrder::factory()->create([
        'items_amount' => '3000.00',
        'shipping_cost' => '0.00',
        'total_amount' => '3000.00',
        'status' => WebOrderStatus::Pending,
        'paid_at' => null,
        'paid_amount' => null,
    ]);

    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $this->article->id,
        'quantity' => '1.000',
        'unit_price' => '3000.00',
        'price_list_id' => $this->onlinePriceList->id,
        'line_total' => '3000.00',
    ]);

    $paidAt = Carbon::parse('2026-10-08 14:30:00');
    $updated = $this->action->execute($order, 'pay-998877', '3000.00', $paidAt);

    expect($updated->status)->toBe(WebOrderStatus::Paid)
        ->and($updated->mp_payment_id)->toBe('pay-998877')
        ->and($updated->paid_amount)->toBe('3000.00')
        ->and($updated->paid_at->toDateTimeString())->toBe('2026-10-08 14:30:00');

    expect($order->fresh()->status)->toBe(WebOrderStatus::Paid)
        ->and($order->fresh()->paid_amount)->toBe('3000.00');

    // Asserts EPIC-15: sale and invoice created
    $sale = Sale::query()->where('web_order_id', $order->id)->first();
    expect($sale)->not->toBeNull()
        ->and($sale->status)->toBe(SaleStatus::Confirmed)
        ->and($sale->channel)->toBe(SaleChannel::Online)
        ->and($sale->total_amount)->toBe('3000.00')
        ->and($sale->invoice)->not->toBeNull()
        ->and($sale->invoice->total_amount)->toBe('3000.00');
});

test('is idempotent when called multiple times with the same payment id', function () {
    $paidAt = Carbon::parse('2026-10-08 14:30:00');
    $order = WebOrder::factory()->paid()->create([
        'items_amount' => '5000.00',
        'shipping_cost' => '0.00',
        'total_amount' => '5000.00',
        'paid_amount' => '5000.00',
        'mp_payment_id' => 'pay-repeat-123',
        'paid_at' => $paidAt,
    ]);

    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $this->article->id,
        'quantity' => '1.000',
        'unit_price' => '5000.00',
        'price_list_id' => $this->onlinePriceList->id,
        'line_total' => '5000.00',
    ]);

    // Replay exact same notification
    $result = $this->action->execute($order, 'pay-repeat-123', '5000.00', Carbon::parse('2026-10-08 15:00:00'));

    expect($result->id)->toBe($order->id)
        ->and($result->status)->toBe(WebOrderStatus::Paid)
        ->and($result->mp_payment_id)->toBe('pay-repeat-123')
        ->and($result->paid_at->toDateTimeString())->toBe('2026-10-08 14:30:00');

    expect(Sale::query()->where('web_order_id', $order->id)->count())->toBe(1);
});

test('rejects payment when paid amount does not match order total', function () {
    $order = WebOrder::factory()->create([
        'items_amount' => '3000.00',
        'shipping_cost' => '0.00',
        'total_amount' => '3000.00',
    ]);

    expect(fn () => $this->action->execute($order, 'pay-998877', '2999.00', now()))
        ->toThrow(InvalidArgumentException::class, 'no coincide con el total del pedido');

    expect($order->fresh()->status)->toBe(WebOrderStatus::Pending);
});

test('rejects paying order with a different payment id if already paid', function () {
    $order = WebOrder::factory()->paid()->create([
        'items_amount' => '3000.00',
        'shipping_cost' => '0.00',
        'total_amount' => '3000.00',
        'paid_amount' => '3000.00',
        'mp_payment_id' => 'pay-first-111',
    ]);

    expect(fn () => $this->action->execute($order, 'pay-second-222', '3000.00', now()))
        ->toThrow(RuntimeException::class, 'ya se encuentra pagado con otro pago de Mercado Pago');
});
