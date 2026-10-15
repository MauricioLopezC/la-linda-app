<?php

use App\Actions\Ecommerce\ConvertPaidWebOrderToSale;
use App\Enums\Catalog\ArticleStatus;
use App\Enums\Customers\CustomerIdType;
use App\Enums\Customers\CustomerTaxCondition;
use App\Enums\Ecommerce\DeliveryMethod;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Sales\InvoiceType;
use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\VatRate;
use App\Models\Sales\Invoice;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

beforeEach(function () {
    $this->action = app(ConvertPaidWebOrderToSale::class);

    $this->vat21 = VatRate::query()->where('percentage', 21.0)->first()
        ?? VatRate::factory()->create(['percentage' => 21.0, 'description' => 'IVA 21%']);

    $this->onlineWarehouse = Warehouse::factory()->online()->create([
        'name' => 'Depósito E-commerce Test',
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

test('converts paid web order into confirmed online sale with an issued invoice', function () {
    $customer = Customer::factory()->create([
        'tax_condition' => CustomerTaxCondition::ConsumidorFinal,
    ]);

    $order = WebOrder::factory()->paid()->create([
        'customer_id' => $customer->id,
        'delivery_method' => DeliveryMethod::Pickup,
        'items_amount' => '2420.00',
        'shipping_cost' => '0.00',
        'total_amount' => '2420.00',
        'paid_amount' => '2420.00',
        'mp_payment_id' => 'mp-test-1001',
    ]);

    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $this->article->id,
        'quantity' => '2.000',
        'unit_price' => '1210.00',
        'price_list_id' => $this->onlinePriceList->id,
        'line_total' => '2420.00',
    ]);

    $sale = $this->action->execute($order);

    expect($sale)->toBeInstanceOf(Sale::class)
        ->and($sale->status)->toBe(SaleStatus::Confirmed)
        ->and($sale->channel)->toBe(SaleChannel::Online)
        ->and($sale->cash_session_id)->toBeNull()
        ->and($sale->user_id)->toBeNull()
        ->and($sale->web_order_id)->toBe($order->id)
        ->and($sale->point_of_sale_id)->toBe($this->onlinePos->id)
        ->and($sale->total_amount)->toBe('2420.00')
        ->and($sale->shipping_amount)->toBe('0.00')
        ->and($sale->items)->toHaveCount(1);

    // Check invoice
    $invoice = $sale->invoice;
    expect($invoice)->not->toBeNull()
        ->and($invoice->type)->toBe(InvoiceType::B)
        ->and($invoice->number)->toBe(1)
        ->and($invoice->point_of_sale_id)->toBe($this->onlinePos->id)
        ->and($invoice->point_of_sale_number)->toBe($this->onlinePos->number)
        ->and($invoice->user_id)->toBeNull()
        ->and($invoice->cash_session_id)->toBeNull()
        ->and($invoice->net_amount)->toBe('2000.00')
        ->and($invoice->vat_amount)->toBe('420.00')
        ->and($invoice->total_amount)->toBe('2420.00')
        ->and($invoice->items)->toHaveCount(1);
});

test('handles order with shipping cost by adding shipping_amount and an invoice concept item', function () {
    $customer = Customer::factory()->create([
        'tax_condition' => CustomerTaxCondition::ConsumidorFinal,
    ]);

    $order = WebOrder::factory()->paid()->shipping('1210.00')->create([
        'customer_id' => $customer->id,
        'items_amount' => '1210.00',
        'shipping_cost' => '1210.00',
        'total_amount' => '2420.00',
        'paid_amount' => '2420.00',
        'mp_payment_id' => 'mp-test-shipping-1',
    ]);

    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $this->article->id,
        'quantity' => '1.000',
        'unit_price' => '1210.00',
        'price_list_id' => $this->onlinePriceList->id,
        'line_total' => '1210.00',
    ]);

    $sale = $this->action->execute($order);

    expect($sale->shipping_amount)->toBe('1210.00')
        ->and($sale->total_amount)->toBe('2420.00');

    $invoice = $sale->invoice;
    expect($invoice)->not->toBeNull()
        ->and($invoice->total_amount)->toBe('2420.00')
        ->and($invoice->items)->toHaveCount(2);

    // Concept line for shipping
    $shippingItem = $invoice->items->firstWhere('article_id', null);
    expect($shippingItem)->not->toBeNull()
        ->and($shippingItem->description)->toBe('Envío a domicilio')
        ->and($shippingItem->quantity)->toBe('1.000')
        ->and($shippingItem->unit_price)->toBe('1210.00')
        ->and($shippingItem->vat_rate)->toBe('21.00')
        ->and($shippingItem->net_amount)->toBe('1000.00')
        ->and($shippingItem->vat_amount)->toBe('210.00')
        ->and($shippingItem->line_total)->toBe('1210.00');

    // Totals match exactly
    expect((float) $invoice->net_amount + (float) $invoice->vat_amount)->toEqual((float) $invoice->total_amount);
});

test('issues invoice type A for responsible inscripto customer with valid cuit', function () {
    $customer = Customer::factory()->create([
        'tax_condition' => CustomerTaxCondition::ResponsibleInscripto,
        'id_type' => CustomerIdType::Cuit,
        'id_number' => '30712345678',
    ]);

    $order = WebOrder::factory()->paid()->create([
        'customer_id' => $customer->id,
        'items_amount' => '1210.00',
        'shipping_cost' => '0.00',
        'total_amount' => '1210.00',
        'paid_amount' => '1210.00',
    ]);

    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $this->article->id,
        'quantity' => '1.000',
        'unit_price' => '1210.00',
        'price_list_id' => $this->onlinePriceList->id,
        'line_total' => '1210.00',
    ]);

    $sale = $this->action->execute($order);

    expect($sale->invoice->type)->toBe(InvoiceType::A);
});

test('is strictly idempotent and returns existing sale without duplicating', function () {
    $customer = Customer::factory()->create();

    $order = WebOrder::factory()->paid()->create([
        'customer_id' => $customer->id,
        'items_amount' => '1210.00',
        'shipping_cost' => '0.00',
        'total_amount' => '1210.00',
        'paid_amount' => '1210.00',
    ]);

    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $this->article->id,
        'quantity' => '1.000',
        'unit_price' => '1210.00',
        'price_list_id' => $this->onlinePriceList->id,
        'line_total' => '1210.00',
    ]);

    $firstSale = $this->action->execute($order);
    $secondSale = $this->action->execute($order);

    expect($firstSale->id)->toBe($secondSale->id);
    expect(Sale::query()->where('web_order_id', $order->id)->count())->toBe(1);
    expect(Invoice::query()->where('sale_id', $firstSale->id)->count())->toBe(1);
});

test('does not discount warehouse stock upon payment conversion', function () {
    $stockBalance = StockBalance::factory()->create([
        'warehouse_id' => $this->onlineWarehouse->id,
        'article_id' => $this->article->id,
        'quantity' => '50.000',
    ]);

    $order = WebOrder::factory()->paid()->create([
        'items_amount' => '1210.00',
        'shipping_cost' => '0.00',
        'total_amount' => '1210.00',
        'paid_amount' => '1210.00',
    ]);

    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $this->article->id,
        'quantity' => '5.000',
        'unit_price' => '242.00',
        'price_list_id' => $this->onlinePriceList->id,
        'line_total' => '1210.00',
    ]);

    $sale = $this->action->execute($order);

    // Stock stays at 50, not deducted
    expect($stockBalance->fresh()->quantity)->toBe('50.000');
    expect(StockMovement::query()->where('sale_id', $sale->id)->exists())->toBeFalse();
});

test('throws invalid argument exception if order is not paid', function () {
    $order = WebOrder::factory()->create([
        'status' => WebOrderStatus::Pending,
    ]);

    expect(fn () => $this->action->execute($order))
        ->toThrow(InvalidArgumentException::class, 'Solo se puede procesar como venta un pedido web en estado pagado');
});

test('throws runtime exception if no online warehouse is configured', function () {
    $this->onlineWarehouse->update(['is_online_channel' => false]);

    $order = WebOrder::factory()->paid()->create();

    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $this->article->id,
    ]);

    expect(fn () => $this->action->execute($order))
        ->toThrow(RuntimeException::class, 'No se encontró un depósito activo asignado al canal online');
});

test('rejects an article that lost its VAT rate after checkout', function (bool $hasStandardRate) {
    $order = WebOrder::factory()->paid()->create([
        'items_amount' => '121.00',
        'total_amount' => '121.00',
        'paid_amount' => '121.00',
    ]);
    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $this->article->id,
        'price_list_id' => $this->onlinePriceList->id,
        'unit_price' => '121.00',
        'line_total' => '121.00',
    ]);
    $this->article->update(['status' => ArticleStatus::Inactive, 'vat_rate_id' => null]);
    if (! $hasStandardRate) {
        $this->vat21->update(['percentage' => 0]);
    }

    expect(fn () => $this->action->execute($order))
        ->toThrow(ValidationException::class, 'El artículo "'.$this->article->description.'" no tiene alícuota de IVA asignada.');

    expect(Sale::query()->where('web_order_id', $order->id)->exists())->toBeFalse()
        ->and(SaleItem::count())->toBe(0)
        ->and(Invoice::count())->toBe(0);
})->with(['standard VAT exists' => true, 'standard VAT is absent' => false]);

test('preserves frozen prices and mixed VAT rates when converting and replaying an order', function () {
    $order = WebOrder::factory()->paid()->shipping('121.00')->create([
        'items_amount' => '210.50',
        'total_amount' => '331.50',
        'paid_amount' => '331.50',
    ]);
    $itemList = PriceList::factory()->particular()->inactive()->create();
    $orderItems = collect([['0.00', '100.00'], ['10.50', '110.50']])->map(function (array $line) use ($order, $itemList): WebOrderItem {
        $rate = VatRate::factory()->create(['percentage' => (float) $line[0]]);
        $article = Article::factory()->create(['vat_rate_id' => $rate->id]);

        return WebOrderItem::factory()->create([
            'web_order_id' => $order->id,
            'article_id' => $article->id,
            'price_list_id' => $itemList->id,
            'quantity' => '1.000',
            'unit_price' => $line[1],
            'line_total' => $line[1],
        ]);
    });
    $orderItems->first()->article->update(['status' => ArticleStatus::Inactive]);

    $sale = $this->action->execute($order);
    $invoiceId = $sale->invoice->id;
    $breakdown = collect($sale->getVatBreakdown())->keyBy('vat_rate');

    expect($sale->price_list_id)->toBe($itemList->id)
        ->and($sale->items->pluck('price_list_id')->all())->toBe([$itemList->id, $itemList->id])
        ->and($sale->items->pluck('unit_price')->all())->toBe(['100.00', '110.50'])
        ->and($sale->items->pluck('quantity')->all())->toBe(['1.000', '1.000'])
        ->and($sale->items->pluck('line_total')->all())->toBe(['100.00', '110.50'])
        ->and($breakdown->keys()->all())->toBe(['21.00', '10.50', '0.00'])
        ->and($breakdown->pluck('net_amount')->all())->toBe(['100.00', '100.00', '100.00'])
        ->and($breakdown->pluck('vat_amount')->all())->toBe(['21.00', '10.50', '0.00'])
        ->and($sale->invoice->net_amount)->toBe('300.00')
        ->and($sale->invoice->vat_amount)->toBe('31.50')
        ->and($sale->invoice->total_amount)->toBe($order->paid_amount)
        ->and($sale->invoice->items)->toHaveCount(3);

    $orderItems->first()->article->update(['vat_rate_id' => null]);
    $replayed = $this->action->execute($order->fresh());

    expect($replayed->id)->toBe($sale->id)
        ->and($replayed->invoice->id)->toBe($invoiceId)
        ->and($replayed->items->pluck('vat_rate')->all())->toBe(['0.00', '10.50'])
        ->and(Sale::query()->where('web_order_id', $order->id)->count())->toBe(1)
        ->and(Invoice::query()->where('sale_id', $sale->id)->count())->toBe(1);
});

test('keeps the price list fallback for legacy orders without lines', function (string $source) {
    $customerList = $source === 'customer' ? PriceList::factory()->particular()->create() : null;
    $customer = Customer::factory()->create(['price_list_id' => $customerList?->id]);
    if ($source !== 'online') {
        $this->onlinePriceList->update(['is_active' => false]);
    }
    $order = WebOrder::factory()->paid()->create(['customer_id' => $customer->id]);

    $sale = $this->action->execute($order);

    expect($sale->price_list_id)->toBe($source === 'customer' ? $customerList->id : $this->onlinePriceList->id);
})->with(['online', 'customer', 'first available']);
