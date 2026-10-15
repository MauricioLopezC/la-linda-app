<?php

use App\Actions\Ecommerce\DeliverWebOrder;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Security\UserRole;
use App\Models\Catalog\Article;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementType;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->operator = User::factory()->create([
        'role' => UserRole::Deposito,
    ]);

    $this->warehouse = Warehouse::factory()->create([
        'is_online_channel' => true,
        'is_active' => true,
    ]);

    $this->movementType = StockMovementType::query()
        ->where('code', StockMovementType::CODE_WEB_ORDER_DELIVERY)
        ->first() ?? StockMovementType::create([
            'name' => 'Salida por Entrega de Pedido Web',
            'name_normalized' => 'salida por entrega de pedido web',
            'code' => StockMovementType::CODE_WEB_ORDER_DELIVERY,
            'sign' => -1,
            'description' => 'Salida de mercadería por entrega o despacho de pedido web',
            'is_system' => true,
            'is_active' => true,
        ]);

    $this->action = app(DeliverWebOrder::class);
});

test('transitions an in_preparation order to delivered recording timestamp and operator', function () {
    $order = WebOrder::factory()->inPreparation()->create();

    $updated = $this->action->execute($order, $this->operator);

    expect($updated->status)->toBe(WebOrderStatus::Delivered)
        ->and($updated->delivered_at)->not->toBeNull()
        ->and($updated->delivered_by)->toBe($this->operator->id);

    $this->assertDatabaseHas('web_orders', [
        'id' => $order->id,
        'status' => WebOrderStatus::Delivered->value,
        'delivered_by' => $this->operator->id,
    ]);
});

test('rejects delivery if order is not in_preparation', function () {
    $pendingOrder = WebOrder::factory()->create([
        'status' => WebOrderStatus::Pending,
    ]);
    expect(fn () => $this->action->execute($pendingOrder, $this->operator))
        ->toThrow(ValidationException::class);

    $paidOrder = WebOrder::factory()->paid()->create();
    expect(fn () => $this->action->execute($paidOrder, $this->operator))
        ->toThrow(ValidationException::class);

    $deliveredOrder = WebOrder::factory()->delivered()->create();
    expect(fn () => $this->action->execute($deliveredOrder, $this->operator))
        ->toThrow(ValidationException::class);
});

test('creates stock movement of type web_order_delivery and discounts online warehouse stock', function () {
    $article = Article::factory()->create();

    StockBalance::factory()->create([
        'article_id' => $article->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => '10.000',
    ]);

    $order = WebOrder::factory()->inPreparation()->create();
    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $article->id,
        'quantity' => '3.000',
    ]);

    $updated = $this->action->execute($order, $this->operator);

    expect($updated->status)->toBe(WebOrderStatus::Delivered);

    $movement = StockMovement::query()
        ->where('stock_movement_type_id', $this->movementType->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->notes)->toContain((string) $order->formattedNumber())
        ->and($movement->items)->toHaveCount(1);

    $item = $movement->items->first();
    expect($item->article_id)->toBe($article->id)
        ->and((float) $item->quantity)->toBe(-3.0)
        ->and((float) $item->system_quantity)->toBe(10.0)
        ->and($item->hasStockConflict())->toBeFalse();

    $balance = StockBalance::query()
        ->where('article_id', $article->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect((float) $balance->quantity)->toBe(7.0);
});

test('links stock movement to associated sale', function () {
    $article = Article::factory()->create();

    StockBalance::factory()->create([
        'article_id' => $article->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => '15.000',
    ]);

    $order = WebOrder::factory()->inPreparation()->create();
    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $article->id,
        'quantity' => '4.000',
    ]);

    $pos = PointOfSale::factory()->create([
        'warehouse_id' => $this->warehouse->id,
    ]);

    $sale = Sale::factory()->online()->create([
        'web_order_id' => $order->id,
        'customer_id' => $order->customer_id,
        'point_of_sale_id' => $pos->id,
    ]);

    $this->action->execute($order, $this->operator);

    $movement = StockMovement::query()
        ->where('sale_id', $sale->id)
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->stock_movement_type_id)->toBe($this->movementType->id);
});

test('is idempotent and does not duplicate stock deduction if already discounted', function () {
    $article = Article::factory()->create();

    StockBalance::factory()->create([
        'article_id' => $article->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => '10.000',
    ]);

    $order = WebOrder::factory()->inPreparation()->create();
    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $article->id,
        'quantity' => '2.000',
    ]);

    $pos = PointOfSale::factory()->create([
        'warehouse_id' => $this->warehouse->id,
    ]);

    $sale = Sale::factory()->online()->create([
        'web_order_id' => $order->id,
        'customer_id' => $order->customer_id,
        'point_of_sale_id' => $pos->id,
    ]);

    // Simulate stock movement already created
    StockMovement::factory()->create([
        'sale_id' => $sale->id,
        'stock_movement_type_id' => $this->movementType->id,
        'warehouse_id' => $this->warehouse->id,
        'user_id' => $this->operator->id,
    ]);

    $this->action->execute($order, $this->operator);

    expect(StockMovement::query()->where('sale_id', $sale->id)->count())->toBe(1);

    $balance = StockBalance::query()
        ->where('article_id', $article->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    // Did not decrement a second time
    expect((float) $balance->quantity)->toBe(10.0);
});

test('applies EPIC-06 conflict rule without blocking delivery when stock is insufficient', function () {
    $article = Article::factory()->create();

    // Balance has only 1.000
    StockBalance::factory()->create([
        'article_id' => $article->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => '1.000',
    ]);

    $order = WebOrder::factory()->inPreparation()->create();
    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $article->id,
        'quantity' => '5.000',
    ]);

    $updated = $this->action->execute($order, $this->operator);

    expect($updated->status)->toBe(WebOrderStatus::Delivered);

    $movement = StockMovement::query()
        ->where('stock_movement_type_id', $this->movementType->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($movement)->not->toBeNull();

    $item = $movement->items->first();
    expect((float) $item->quantity)->toBe(-5.0)
        ->and((float) $item->system_quantity)->toBe(1.0)
        ->and($item->hasStockConflict())->toBeTrue()
        ->and($movement->hasStockConflict())->toBeTrue();

    $balance = StockBalance::query()
        ->where('article_id', $article->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    // Balance left negative at -4.000
    expect((float) $balance->quantity)->toBe(-4.0);
});
