<?php

use App\Models\Catalog\Article;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementItem;
use App\Models\Inventory\StockMovementType;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('a stock balance is unique per article and warehouse', function () {
    $article = Article::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockBalance::factory()->create([
        'article_id' => $article->id,
        'warehouse_id' => $warehouse->id,
    ]);

    expect(fn () => StockBalance::factory()->create([
        'article_id' => $article->id,
        'warehouse_id' => $warehouse->id,
    ]))->toThrow(QueryException::class);
});

test('a stock balance cannot go negative', function () {
    $balance = StockBalance::factory()->create(['quantity' => 5]);

    expect(fn () => $balance->update(['quantity' => -1]))->toThrow(QueryException::class);
});

test('a stock balance keeps decimal quantities for articles sold by weight', function () {
    $balance = StockBalance::factory()->create(['quantity' => 1.5]);

    expect($balance->fresh()->quantity)->toBe('1.500');
});

test('a movement line cannot have a zero quantity', function () {
    expect(fn () => StockMovementItem::factory()->create(['quantity' => 0]))
        ->toThrow(QueryException::class);
});

test('a movement line stores the sign of the delta', function () {
    $item = StockMovementItem::factory()->state(['quantity' => 10])->outgoing()->create();

    expect($item->fresh()->quantity)->toBe('-10.000');
});

test('an article cannot be repeated within the same movement', function () {
    $movement = StockMovement::factory()->create();
    $article = Article::factory()->create();

    StockMovementItem::factory()->create([
        'stock_movement_id' => $movement->id,
        'article_id' => $article->id,
    ]);

    expect(fn () => StockMovementItem::factory()->create([
        'stock_movement_id' => $movement->id,
        'article_id' => $article->id,
    ]))->toThrow(QueryException::class);
});

test('a stock movement is created without an updated_at column', function () {
    $movement = StockMovement::factory()->create();

    expect(Schema::hasColumn('stock_movements', 'updated_at'))->toBeFalse()
        ->and($movement->created_at)->not->toBeNull();

    $movement->touch();

    expect($movement->fresh())->not->toBeNull();
});

test('an adjustment movement links its type, warehouse, user and items', function () {
    $movement = StockMovement::factory()->adjustment()->create();
    StockMovementItem::factory()->count(2)->create(['stock_movement_id' => $movement->id]);

    $movement->load(['type', 'warehouse', 'user', 'items']);

    expect($movement->type->sign)->toBe(-1)
        ->and($movement->warehouse)->toBeInstanceOf(Warehouse::class)
        ->and($movement->user)->not->toBeNull()
        ->and($movement->items)->toHaveCount(2);
});

test('a warehouse with stock or movements blocks its deactivation', function () {
    $empty = Warehouse::factory()->create();
    expect($empty->hasRegisteredStock())->toBeFalse();

    StockBalance::factory()->outOfStock()->create(['warehouse_id' => $empty->id]);
    expect($empty->hasRegisteredStock())->toBeFalse();

    $withStock = Warehouse::factory()->create();
    StockBalance::factory()->create(['warehouse_id' => $withStock->id, 'quantity' => 3]);
    expect($withStock->hasRegisteredStock())->toBeTrue();

    $withMovements = Warehouse::factory()->create();
    StockMovement::factory()->create(['warehouse_id' => $withMovements->id]);
    expect($withMovements->hasRegisteredStock())->toBeTrue();
});

test('a branch reports registered stock through its warehouses', function () {
    $branch = Branch::factory()->create();
    $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id]);

    expect($branch->hasRegisteredStock())->toBeFalse();

    StockBalance::factory()->create(['warehouse_id' => $warehouse->id, 'quantity' => 7]);

    expect($branch->hasRegisteredStock())->toBeTrue();
});

test('a movement type reports being in use', function () {
    $type = StockMovementType::factory()->create();

    expect($type->isInUse())->toBeFalse();

    StockMovement::factory()->create([
        'stock_movement_type_id' => $type->id,
    ]);

    expect($type->isInUse())->toBeTrue();
});

test('an article reports its stock movements', function () {
    $article = Article::factory()->create();

    expect($article->hasStockMovements())->toBeFalse();

    StockMovementItem::factory()->create(['article_id' => $article->id]);

    expect($article->hasStockMovements())->toBeTrue();
});

test('a warehouse cannot be deleted while it holds a balance', function () {
    $warehouse = Warehouse::factory()->create();
    StockBalance::factory()->create(['warehouse_id' => $warehouse->id]);

    expect(fn () => $warehouse->delete())->toThrow(QueryException::class);
});

test('a movement cannot be deleted while it has lines', function () {
    $movement = StockMovement::factory()->create();
    StockMovementItem::factory()->create(['stock_movement_id' => $movement->id]);

    expect(fn () => $movement->delete())->toThrow(QueryException::class);
});
