<?php

use App\Actions\Inventory\ConsultArticleKardex;
use App\Actions\Inventory\ConsultStockMovements;
use App\Actions\Inventory\CreateStockMovementFromSale;
use App\Actions\Inventory\RegisterStockAdjustment;
use App\Actions\Sales\ConfirmSalePayment;
use App\Enums\Sales\CashSessionStatus;
use App\Enums\Sales\PaymentMethodKind;
use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Catalog\Article;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementItem;
use App\Models\Inventory\StockMovementType;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\CashSession;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\User;
use Database\Seeders\Inventory\StockMovementTypeSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(StockMovementTypeSeeder::class);
});

function createSaleForStockTest(Article $article, float $quantity = 2.0, float $initialStock = 10.0): array
{
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['name' => 'Depósito Central']);
    $pointOfSale = PointOfSale::factory()->create(['warehouse_id' => $warehouse->id]);

    $cashSession = CashSession::factory()->create([
        'point_of_sale_id' => $pointOfSale->id,
        'user_id' => $user->id,
        'status' => CashSessionStatus::Open,
    ]);

    $totalAmount = number_format($quantity * 500, 2, '.', '');

    $sale = Sale::factory()->create([
        'point_of_sale_id' => $pointOfSale->id,
        'user_id' => $user->id,
        'cash_session_id' => $cashSession->id,
        'channel' => SaleChannel::Mostrador,
        'status' => SaleStatus::Open,
        'total_amount' => $totalAmount,
    ]);

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'article_id' => $article->id,
        'quantity' => sprintf('%.3f', $quantity),
        'unit_price' => '500.00',
        'line_total' => $totalAmount,
    ]);

    $sale->recalculateTotal();

    if ($initialStock > 0) {
        StockBalance::create([
            'warehouse_id' => $warehouse->id,
            'article_id' => $article->id,
            'quantity' => sprintf('%.3f', $initialStock),
        ]);
    }

    return [$user, $sale, $warehouse, $pointOfSale];
}

test('confirming a sale automatically creates a sale_exit stock movement and discounts stock_balances', function () {
    $article = Article::factory()->create(['description' => 'Leche Descremada']);
    [$user, $sale, $warehouse] = createSaleForStockTest($article, quantity: 3.0, initialStock: 10.0);

    $cashMethod = PaymentMethod::factory()->create([
        'name' => 'Efectivo',
        'kind' => PaymentMethodKind::Cash,
        'is_active' => true,
    ]);

    app(ConfirmSalePayment::class)->handle($sale, [
        [
            'payment_method_id' => $cashMethod->id,
            'amount' => $sale->total_amount,
            'tendered_amount' => null,
        ],
    ], $user);

    expect($sale->fresh()->status)->toBe(SaleStatus::Confirmed);

    // Verify stock movement
    /** @var StockMovement|null $movement */
    $movement = StockMovement::query()->where('sale_id', $sale->id)->first();
    expect($movement)->not->toBeNull()
        ->and($movement->stock_movement_type_id)->toBe(StockMovementType::where('code', StockMovementType::CODE_SALE_EXIT)->value('id'))
        ->and($movement->warehouse_id)->toBe($warehouse->id)
        ->and($movement->user_id)->toBe($user->id)
        ->and($movement->notes)->toBe("Salida automática por venta #{$sale->id}");

    // Verify movement line
    /** @var StockMovementItem|null $item */
    $item = $movement->items()->first();
    expect($item)->not->toBeNull()
        ->and($item->article_id)->toBe($article->id)
        ->and((float) $item->quantity)->toBe(-3.000)
        ->and((float) $item->system_quantity)->toBe(10.000)
        ->and($item->hasStockConflict())->toBeFalse();

    // Verify updated stock balance
    $balance = StockBalance::query()
        ->where('warehouse_id', $warehouse->id)
        ->where('article_id', $article->id)
        ->first();

    expect((float) $balance->quantity)->toBe(7.000);
});

test('sale stock deduction is idempotent and generates only one stock movement', function () {
    $article = Article::factory()->create();
    [$user, $sale, $warehouse] = createSaleForStockTest($article, quantity: 2.0, initialStock: 5.0);

    $cashMethod = PaymentMethod::factory()->create([
        'kind' => PaymentMethodKind::Cash,
        'is_active' => true,
    ]);

    app(ConfirmSalePayment::class)->handle($sale, [
        [
            'payment_method_id' => $cashMethod->id,
            'amount' => $sale->total_amount,
            'tendered_amount' => null,
        ],
    ], $user);

    expect(StockMovement::where('sale_id', $sale->id)->count())->toBe(1);

    // Manually invoke CreateStockMovementFromSale again
    $movement = app(CreateStockMovementFromSale::class)->handle($sale, $user->id);

    expect(StockMovement::where('sale_id', $sale->id)->count())->toBe(1)
        ->and($movement->id)->toBe(StockMovement::where('sale_id', $sale->id)->value('id'));

    // Balance was only decremented once
    $balance = StockBalance::where('warehouse_id', $warehouse->id)->where('article_id', $article->id)->value('quantity');
    expect((float) $balance)->toBe(3.000);
});

test('missing stock does not block the sale: confirming sale with zero stock leaves negative balance and marks conflict', function () {
    $article = Article::factory()->create(['description' => 'Arroz Largo Fino']);
    [$user, $sale, $warehouse] = createSaleForStockTest($article, quantity: 4.0, initialStock: 0.0);

    $cashMethod = PaymentMethod::factory()->create([
        'kind' => PaymentMethodKind::Cash,
        'is_active' => true,
    ]);

    // The sale confirms without error!
    app(ConfirmSalePayment::class)->handle($sale, [
        [
            'payment_method_id' => $cashMethod->id,
            'amount' => $sale->total_amount,
            'tendered_amount' => null,
        ],
    ], $user);

    expect($sale->fresh()->status)->toBe(SaleStatus::Confirmed);

    /** @var StockMovement $movement */
    $movement = StockMovement::query()->where('sale_id', $sale->id)->firstOrFail();
    $item = $movement->items()->firstOrFail();

    expect((float) $item->quantity)->toBe(-4.000)
        ->and((float) $item->system_quantity)->toBe(0.000)
        ->and($item->hasStockConflict())->toBeTrue()
        ->and($movement->hasStockConflict())->toBeTrue();

    // Balance is negative
    $balance = StockBalance::where('warehouse_id', $warehouse->id)->where('article_id', $article->id)->value('quantity');
    expect((float) $balance)->toBe(-4.000);
});

test('insufficient stock leaves balance negative and marks conflict with prior system quantity', function () {
    $article = Article::factory()->create();
    [$user, $sale, $warehouse] = createSaleForStockTest($article, quantity: 5.0, initialStock: 2.0);

    $cashMethod = PaymentMethod::factory()->create([
        'kind' => PaymentMethodKind::Cash,
        'is_active' => true,
    ]);

    app(ConfirmSalePayment::class)->handle($sale, [
        [
            'payment_method_id' => $cashMethod->id,
            'amount' => $sale->total_amount,
            'tendered_amount' => null,
        ],
    ], $user);

    $movement = StockMovement::query()->where('sale_id', $sale->id)->firstOrFail();
    $item = $movement->items()->firstOrFail();

    expect((float) $item->quantity)->toBe(-5.000)
        ->and((float) $item->system_quantity)->toBe(2.000)
        ->and($item->hasStockConflict())->toBeTrue();

    $balance = StockBalance::where('warehouse_id', $warehouse->id)->where('article_id', $article->id)->value('quantity');
    expect((float) $balance)->toBe(-3.000);
});

test('open or discarded sales do not move stock', function () {
    $article = Article::factory()->create();
    [$user, $openSale, $warehouse] = createSaleForStockTest($article, quantity: 1.0, initialStock: 5.0);

    // Trying to move stock on open sale throws
    expect(fn () => app(CreateStockMovementFromSale::class)->handle($openSale, $user->id))
        ->toThrow(ValidationException::class);

    $discardedSale = Sale::factory()->create([
        'point_of_sale_id' => $openSale->point_of_sale_id,
        'user_id' => $user->id,
        'status' => SaleStatus::Discarded,
    ]);

    SaleItem::factory()->create([
        'sale_id' => $discardedSale->id,
        'article_id' => $article->id,
        'quantity' => '1.000',
    ]);

    expect(fn () => app(CreateStockMovementFromSale::class)->handle($discardedSale, $user->id))
        ->toThrow(ValidationException::class);

    expect(StockMovement::count())->toBe(0);
});

test('stock movement is immutable and linked to the sale in both directions', function () {
    $article = Article::factory()->create();
    [$user, $sale] = createSaleForStockTest($article, quantity: 1.0, initialStock: 5.0);

    $cashMethod = PaymentMethod::factory()->create(['kind' => PaymentMethodKind::Cash, 'is_active' => true]);

    app(ConfirmSalePayment::class)->handle($sale, [
        ['payment_method_id' => $cashMethod->id, 'amount' => $sale->total_amount],
    ], $user);

    $movement = StockMovement::where('sale_id', $sale->id)->firstOrFail();

    expect($movement->sale)->not->toBeNull()
        ->and($movement->sale->id)->toBe($sale->id)
        ->and($sale->fresh()->stockMovement)->not->toBeNull()
        ->and($sale->fresh()->stockMovement->id)->toBe($movement->id);
});

test('manual stock adjustments still reject negative balances even without database check', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $article = Article::factory()->create();

    StockBalance::create([
        'warehouse_id' => $warehouse->id,
        'article_id' => $article->id,
        'quantity' => '5.000',
    ]);

    $shortageType = StockMovementType::where('code', 'count_shortage')->firstOrFail();

    expect(fn () => app(RegisterStockAdjustment::class)->execute([
        'warehouse_id' => $warehouse->id,
        'stock_movement_type_id' => $shortageType->id,
        'notes' => 'Ajuste excesivo',
        'user_id' => $user->id,
        'items' => [
            ['article_id' => $article->id, 'quantity' => 10.0],
        ],
    ]))->toThrow(ValidationException::class);

    expect((float) StockBalance::where('warehouse_id', $warehouse->id)->where('article_id', $article->id)->value('quantity'))
        ->toBe(5.000);
});

test('kardex and stock movements history reflect sale exit, conflict badge and sale link', function () {
    $article = Article::factory()->create();
    [$user, $sale, $warehouse] = createSaleForStockTest($article, quantity: 3.0, initialStock: 0.0);

    $cashMethod = PaymentMethod::factory()->create(['kind' => PaymentMethodKind::Cash, 'is_active' => true]);

    app(ConfirmSalePayment::class)->handle($sale, [
        ['payment_method_id' => $cashMethod->id, 'amount' => $sale->total_amount],
    ], $user);

    // Kardex
    $kardexResult = app(ConsultArticleKardex::class)->execute($article->id, $warehouse->id);
    expect($kardexResult->total())->toBe(1);

    /** @var StockMovementItem $entry */
    $entry = $kardexResult->first();
    expect($entry->stockMovement->sale_id)->toBe($sale->id)
        ->and((float) $entry->quantity)->toBe(-3.000)
        ->and((float) $entry->system_quantity)->toBe(0.000)
        ->and($entry->hasStockConflict())->toBeTrue();

    // Movements list
    $movementsResult = app(ConsultStockMovements::class)->execute(['warehouse_id' => $warehouse->id]);
    expect($movementsResult->total())->toBe(1);

    /** @var StockMovement $movement */
    $movement = $movementsResult->first();
    expect($movement->sale_id)->toBe($sale->id)
        ->and($movement->hasStockConflict())->toBeTrue();
});
