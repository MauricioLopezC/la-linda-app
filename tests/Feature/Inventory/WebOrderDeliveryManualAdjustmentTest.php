<?php

use App\Actions\Inventory\RegisterStockAdjustment;
use App\Enums\Security\UserRole;
use App\Models\Catalog\Article;
use App\Models\Inventory\StockMovementType;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->operator = User::factory()->create([
        'role' => UserRole::Deposito,
    ]);

    $this->warehouse = Warehouse::factory()->create([
        'is_active' => true,
    ]);

    $this->article = Article::factory()->create();

    $this->webOrderDeliveryType = StockMovementType::query()
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
});

test('web_order_delivery is recognized as automatic system type', function () {
    expect($this->webOrderDeliveryType->isAutomatic())->toBeTrue()
        ->and(in_array(StockMovementType::CODE_WEB_ORDER_DELIVERY, StockMovementType::AUTOMATIC_CODES, true))->toBeTrue();
});

test('manual stock adjustment action rejects web_order_delivery movement type', function () {
    $action = app(RegisterStockAdjustment::class);

    expect(fn () => $action->execute([
        'warehouse_id' => $this->warehouse->id,
        'stock_movement_type_id' => $this->webOrderDeliveryType->id,
        'notes' => 'Intento de ajuste manual no permitido',
        'user_id' => $this->operator->id,
        'items' => [
            [
                'article_id' => $this->article->id,
                'quantity' => '5.000',
            ],
        ],
    ]))->toThrow(ValidationException::class);
});

test('manual stock adjustment HTTP endpoint rejects web_order_delivery', function () {
    $response = $this->actingAs($this->operator)->post(route('inventory.adjustments.store'), [
        'warehouse_id' => $this->warehouse->id,
        'stock_movement_type_id' => $this->webOrderDeliveryType->id,
        'notes' => 'Ajuste manual inválido',
        'items' => [
            [
                'article_id' => $this->article->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['stock_movement_type_id']);
});
