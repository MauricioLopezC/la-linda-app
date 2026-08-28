<?php

use App\Models\Catalog\Article;
use App\Models\Catalog\Category;
use App\Models\Catalog\UnitOfMeasure;
use App\Models\Inventory\StockAdjustmentReason;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementType;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\User;
use Database\Seeders\Catalog\ArticleSeeder;
use Database\Seeders\Catalog\BrandSeeder;
use Database\Seeders\Catalog\CategorySeeder;
use Database\Seeders\Catalog\UnitOfMeasureSeeder;
use Database\Seeders\Inventory\StockAdjustmentReasonSeeder;
use Database\Seeders\Inventory\StockMovementTypeSeeder;
use Database\Seeders\Inventory\WarehouseSeeder;
use Database\Seeders\Inventory\WarehouseStockSeeder;
use Database\Seeders\Organization\BranchSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed([
        StockMovementTypeSeeder::class,
        StockAdjustmentReasonSeeder::class,
    ]);

    $this->user = User::factory()->create();
    $this->branch = Branch::factory()->create(['name' => 'Sucursal Centro']);
    $this->warehouse = Warehouse::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Depósito Principal',
        'is_active' => true,
    ]);

    $this->category = Category::factory()->create(['name' => 'Lácteos']);
    $this->unit = UnitOfMeasure::factory()->create(['name' => 'Unidad', 'abbreviation' => 'UN']);

    $this->articleA = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'internal_code' => 'ART-1001',
        'description' => 'Leche Entera 1L',
        'barcode' => '7790011223344',
    ]);

    $this->articleB = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'internal_code' => 'ART-1002',
        'description' => 'Yogur Firme 190g',
        'barcode' => '7790011223355',
    ]);

    $this->reason = StockAdjustmentReason::query()->where('name', 'Rotura / Daño')->firstOrFail();
});

it('renders the create stock adjustment page for authenticated users', function () {
    $this->actingAs($this->user)
        ->get(route('inventory.adjustments.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('inventory/adjustments/create')
            ->has('warehouses')
            ->has('reasons')
        );
});

it('can search articles with current warehouse stock balance', function () {
    StockBalance::create([
        'warehouse_id' => $this->warehouse->id,
        'article_id' => $this->articleA->id,
        'quantity' => '25.000',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('inventory.adjustments.articles', [
            'warehouse_id' => $this->warehouse->id,
            'search' => 'Leche',
        ]))
        ->assertOk();

    $data = $response->json();
    expect($data)->toHaveCount(1);
    expect($data[0]['internal_code'])->toBe('ART-1001');
    expect($data[0]['current_stock'])->toBe('25.000');
    expect($data[0]['unit_of_measure_abbreviation'])->toBe('UN');
});

it('can search articles by barcode', function () {
    $response = $this->actingAs($this->user)
        ->getJson(route('inventory.adjustments.articles', [
            'warehouse_id' => $this->warehouse->id,
            'search' => '7790011223355',
        ]))
        ->assertOk();

    $data = $response->json();
    expect($data)->toHaveCount(1);
    expect($data[0]['internal_code'])->toBe('ART-1002');
});

it('registers a stock adjustment with positive difference (sobrante) and updates stock balance atomically', function () {
    StockBalance::create([
        'warehouse_id' => $this->warehouse->id,
        'article_id' => $this->articleA->id,
        'quantity' => '10.000',
    ]);

    $response = $this->actingAs($this->user)->post(route('inventory.adjustments.store'), [
        'warehouse_id' => $this->warehouse->id,
        'stock_adjustment_reason_id' => $this->reason->id,
        'notes' => 'Recuento físico arrojó 5 unidades adicionales',
        'items' => [
            [
                'article_id' => $this->articleA->id,
                'counted_quantity' => 15.000,
            ],
        ],
    ]);

    $movement = StockMovement::latest('id')->first();
    expect($movement)->not->toBeNull();

    $response->assertRedirect(route('inventory.adjustments.show', $movement));

    expect($movement->warehouse_id)->toBe($this->warehouse->id);
    expect($movement->stock_adjustment_reason_id)->toBe($this->reason->id);
    expect($movement->user_id)->toBe($this->user->id);
    expect($movement->notes)->toBe('Recuento físico arrojó 5 unidades adicionales');

    $this->assertDatabaseHas('stock_movements', [
        'id' => $movement->id,
        'warehouse_id' => $this->warehouse->id,
        'stock_adjustment_reason_id' => $this->reason->id,
    ]);

    $this->assertDatabaseHas('stock_movement_items', [
        'stock_movement_id' => $movement->id,
        'article_id' => $this->articleA->id,
        'quantity' => 5.000,
        'system_quantity' => 10.000,
    ]);

    $balance = StockBalance::where('warehouse_id', $this->warehouse->id)
        ->where('article_id', $this->articleA->id)
        ->first();

    expect((float) $balance->quantity)->toBe(15.000);
});

it('registers a stock adjustment with negative difference (faltante) and updates stock balance atomically', function () {
    StockBalance::create([
        'warehouse_id' => $this->warehouse->id,
        'article_id' => $this->articleA->id,
        'quantity' => '20.000',
    ]);

    $this->actingAs($this->user)->post(route('inventory.adjustments.store'), [
        'warehouse_id' => $this->warehouse->id,
        'stock_adjustment_reason_id' => $this->reason->id,
        'notes' => 'Mercadería dañada',
        'items' => [
            [
                'article_id' => $this->articleA->id,
                'counted_quantity' => 12.000,
            ],
        ],
    ])->assertRedirect();

    $movement = StockMovement::latest('id')->first();

    $this->assertDatabaseHas('stock_movement_items', [
        'stock_movement_id' => $movement->id,
        'article_id' => $this->articleA->id,
        'quantity' => -8.000,
        'system_quantity' => 20.000,
    ]);

    $balance = StockBalance::where('warehouse_id', $this->warehouse->id)
        ->where('article_id', $this->articleA->id)
        ->first();

    expect((float) $balance->quantity)->toBe(12.000);
});

it('registers a stock adjustment with multiple articles and records proper deltas', function () {
    StockBalance::create([
        'warehouse_id' => $this->warehouse->id,
        'article_id' => $this->articleA->id,
        'quantity' => '10.000',
    ]);
    StockBalance::create([
        'warehouse_id' => $this->warehouse->id,
        'article_id' => $this->articleB->id,
        'quantity' => '5.000',
    ]);

    $this->actingAs($this->user)->post(route('inventory.adjustments.store'), [
        'warehouse_id' => $this->warehouse->id,
        'stock_adjustment_reason_id' => $this->reason->id,
        'items' => [
            [
                'article_id' => $this->articleA->id,
                'counted_quantity' => 14.000, // +4.000
            ],
            [
                'article_id' => $this->articleB->id,
                'counted_quantity' => 2.000, // -3.000
            ],
        ],
    ])->assertRedirect();

    $movement = StockMovement::latest('id')->first();
    expect($movement->items)->toHaveCount(2);

    $this->assertDatabaseHas('stock_movement_items', [
        'stock_movement_id' => $movement->id,
        'article_id' => $this->articleA->id,
        'quantity' => 4.000,
    ]);

    $this->assertDatabaseHas('stock_movement_items', [
        'stock_movement_id' => $movement->id,
        'article_id' => $this->articleB->id,
        'quantity' => -3.000,
    ]);
});

it('validates that stock adjustment reason is required and must exist and be active', function () {
    $inactiveReason = StockAdjustmentReason::factory()->create(['is_active' => false]);

    $this->actingAs($this->user)
        ->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'stock_adjustment_reason_id' => $inactiveReason->id,
            'items' => [
                ['article_id' => $this->articleA->id, 'counted_quantity' => 10],
            ],
        ])
        ->assertSessionHasErrors('stock_adjustment_reason_id');
});

it('validates that warehouse is required and must exist and be active', function () {
    $inactiveWarehouse = Warehouse::factory()->create([
        'branch_id' => $this->branch->id,
        'is_active' => false,
    ]);

    $this->actingAs($this->user)
        ->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $inactiveWarehouse->id,
            'stock_adjustment_reason_id' => $this->reason->id,
            'items' => [
                ['article_id' => $this->articleA->id, 'counted_quantity' => 10],
            ],
        ])
        ->assertSessionHasErrors('warehouse_id');
});

it('validates that at least one article is required in items', function () {
    $this->actingAs($this->user)
        ->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'stock_adjustment_reason_id' => $this->reason->id,
            'items' => [],
        ])
        ->assertSessionHasErrors('items');
});

it('validates that duplicate articles in the same adjustment are rejected', function () {
    $this->actingAs($this->user)
        ->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'stock_adjustment_reason_id' => $this->reason->id,
            'items' => [
                ['article_id' => $this->articleA->id, 'counted_quantity' => 10],
                ['article_id' => $this->articleA->id, 'counted_quantity' => 15],
            ],
        ])
        ->assertSessionHasErrors('items.0.article_id');
});

it('validates that counted quantity cannot be negative', function () {
    $this->actingAs($this->user)
        ->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'stock_adjustment_reason_id' => $this->reason->id,
            'items' => [
                ['article_id' => $this->articleA->id, 'counted_quantity' => -5],
            ],
        ])
        ->assertSessionHasErrors('items.0.counted_quantity');
});

it('validates that counted quantity cannot exceed 100000', function () {
    $this->actingAs($this->user)
        ->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'stock_adjustment_reason_id' => $this->reason->id,
            'items' => [
                ['article_id' => $this->articleA->id, 'counted_quantity' => 100001],
            ],
        ])
        ->assertSessionHasErrors('items.0.counted_quantity');
});

it('rejects adjustment if physical count matches system stock with 0 differences across all items', function () {
    StockBalance::create([
        'warehouse_id' => $this->warehouse->id,
        'article_id' => $this->articleA->id,
        'quantity' => '10.000',
    ]);

    $this->actingAs($this->user)
        ->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'stock_adjustment_reason_id' => $this->reason->id,
            'items' => [
                ['article_id' => $this->articleA->id, 'counted_quantity' => 10.000],
            ],
        ])
        ->assertSessionHasErrors('items');
});

it('enforces immutability by rejecting PUT, PATCH, and DELETE requests on stock movements', function () {
    $movement = StockMovement::create([
        'stock_movement_type_id' => StockMovementType::where('code', StockMovementType::CODE_INVENTORY_ADJUSTMENT)->value('id'),
        'warehouse_id' => $this->warehouse->id,
        'stock_adjustment_reason_id' => $this->reason->id,
        'user_id' => $this->user->id,
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->put("/inventory/adjustments/{$movement->id}", ['notes' => 'New note'])
        ->assertStatus(405);

    $this->actingAs($this->user)
        ->delete("/inventory/adjustments/{$movement->id}")
        ->assertStatus(405);
});

it('guarantees stock_balances has no direct HTTP write routes', function () {
    $this->actingAs($this->user)->post('/inventory/stocks', ['quantity' => 100])->assertStatus(405);
    $this->actingAs($this->user)->put('/inventory/stocks', ['quantity' => 100])->assertStatus(405);
    $this->actingAs($this->user)->patch('/inventory/stocks', ['quantity' => 100])->assertStatus(405);
    $this->actingAs($this->user)->delete('/inventory/stocks', ['quantity' => 100])->assertStatus(405);
});

it('renders the show receipt page with all movement details and formatted dates', function () {
    $movement = StockMovement::create([
        'stock_movement_type_id' => StockMovementType::where('code', StockMovementType::CODE_INVENTORY_ADJUSTMENT)->value('id'),
        'warehouse_id' => $this->warehouse->id,
        'stock_adjustment_reason_id' => $this->reason->id,
        'notes' => 'Comprobante de prueba',
        'user_id' => $this->user->id,
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('inventory.adjustments.show', $movement))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('inventory/adjustments/show')
            ->has('movement')
            ->where('movement.id', $movement->id)
            ->where('movement.notes', 'Comprobante de prueba')
            ->where('movement.warehouse_name', 'Depósito Principal')
        );
});

it('seeds initial inventory using RegisterStockAdjustment with Carga inicial de inventario reason', function () {
    $this->seed([
        BranchSeeder::class,
        WarehouseSeeder::class,
        CategorySeeder::class,
        BrandSeeder::class,
        UnitOfMeasureSeeder::class,
        ArticleSeeder::class,
        WarehouseStockSeeder::class,
    ]);

    $initialReason = StockAdjustmentReason::where('name', 'Carga inicial de inventario')->firstOrFail();
    $movements = StockMovement::where('stock_adjustment_reason_id', $initialReason->id)->get();

    expect($movements)->not->toBeEmpty();
    expect(StockBalance::where('quantity', '>', 0)->count())->toBeGreaterThan(0);
});
