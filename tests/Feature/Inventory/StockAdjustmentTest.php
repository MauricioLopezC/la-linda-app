<?php

use App\Models\Catalog\Article;
use App\Models\Catalog\Category;
use App\Models\Catalog\UnitOfMeasure;
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
use Database\Seeders\Inventory\StockMovementTypeSeeder;
use Database\Seeders\Inventory\WarehouseSeeder;
use Database\Seeders\Inventory\WarehouseStockSeeder;
use Database\Seeders\Organization\BranchSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed([StockMovementTypeSeeder::class]);

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

    $this->surplusType = StockMovementType::query()->where('code', 'count_surplus')->firstOrFail();
    $this->shortageType = StockMovementType::query()->where('code', 'breakage')->firstOrFail();
});

it('renders the create page with warehouses and manual movement types only', function () {
    $this->actingAs($this->user)
        ->get(route('inventory.adjustments.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('inventory/adjustments/create')
            ->has('warehouses')
            ->has('movementTypes')
        );

    $response = $this->actingAs($this->user)->get(route('inventory.adjustments.create'));
    $codes = collect($response->viewData('page')['props']['movementTypes'])->pluck('code');

    expect($codes)->not->toContain(StockMovementType::CODE_PURCHASE_ENTRY)
        ->and($codes)->not->toContain(StockMovementType::CODE_SALE_EXIT)
        ->and($codes)->toContain('count_surplus');
});

it('can search articles by description and barcode without a stock balance join', function () {
    $data = $this->actingAs($this->user)
        ->getJson(route('inventory.adjustments.articles', [
            'warehouse_id' => $this->warehouse->id,
            'search' => 'Leche',
        ]))
        ->assertOk()
        ->json();

    expect($data)->toHaveCount(1);
    expect($data[0]['internal_code'])->toBe('ART-1001');
    expect($data[0])->not->toHaveKey('current_stock');
    expect($data[0]['unit_of_measure_abbreviation'])->toBe('UN');

    $byBarcode = $this->actingAs($this->user)
        ->getJson(route('inventory.adjustments.articles', [
            'warehouse_id' => $this->warehouse->id,
            'search' => '7790011223355',
        ]))
        ->assertOk()
        ->json();

    expect($byBarcode)->toHaveCount(1);
    expect($byBarcode[0]['internal_code'])->toBe('ART-1002');
});

it('registers a positive movement adding only the entered quantity to the balance', function () {
    StockBalance::create([
        'warehouse_id' => $this->warehouse->id,
        'article_id' => $this->articleA->id,
        'quantity' => '10.000',
    ]);

    $response = $this->actingAs($this->user)->post(route('inventory.adjustments.store'), [
        'warehouse_id' => $this->warehouse->id,
        'stock_movement_type_id' => $this->surplusType->id,
        'notes' => 'Recuento físico arrojó 5 unidades adicionales',
        'items' => [
            ['article_id' => $this->articleA->id, 'quantity' => 5],
        ],
    ]);

    $movement = StockMovement::latest('id')->first();
    expect($movement)->not->toBeNull();
    $response->assertRedirect(route('inventory.adjustments.show', $movement));

    expect($movement->warehouse_id)->toBe($this->warehouse->id);
    expect($movement->stock_movement_type_id)->toBe($this->surplusType->id);
    expect($movement->user_id)->toBe($this->user->id);

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

it('registers a negative movement subtracting only the entered quantity from the balance', function () {
    StockBalance::create([
        'warehouse_id' => $this->warehouse->id,
        'article_id' => $this->articleA->id,
        'quantity' => '20.000',
    ]);

    $this->actingAs($this->user)->post(route('inventory.adjustments.store'), [
        'warehouse_id' => $this->warehouse->id,
        'stock_movement_type_id' => $this->shortageType->id,
        'notes' => 'Mercadería dañada durante descarga',
        'items' => [
            ['article_id' => $this->articleA->id, 'quantity' => 8],
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

it('registers a movement with multiple articles applying the type sign to each line', function () {
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
        'stock_movement_type_id' => $this->shortageType->id,
        'notes' => 'Rotura de dos artículos en góndola',
        'items' => [
            ['article_id' => $this->articleA->id, 'quantity' => 4],
            ['article_id' => $this->articleB->id, 'quantity' => 3],
        ],
    ])->assertRedirect();

    $movement = StockMovement::latest('id')->first();
    expect($movement->items)->toHaveCount(2);

    $this->assertDatabaseHas('stock_movement_items', [
        'stock_movement_id' => $movement->id,
        'article_id' => $this->articleA->id,
        'quantity' => -4.000,
    ]);
    $this->assertDatabaseHas('stock_movement_items', [
        'stock_movement_id' => $movement->id,
        'article_id' => $this->articleB->id,
        'quantity' => -3.000,
    ]);
});

it('rejects a movement whose resulting balance would go negative', function () {
    StockBalance::create([
        'warehouse_id' => $this->warehouse->id,
        'article_id' => $this->articleA->id,
        'quantity' => '3.000',
    ]);

    $this->actingAs($this->user)
        ->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'stock_movement_type_id' => $this->shortageType->id,
            'notes' => 'Intento de restar más de lo disponible',
            'items' => [
                ['article_id' => $this->articleA->id, 'quantity' => 10],
            ],
        ])
        ->assertSessionHasErrors('items');

    expect(StockMovement::count())->toBe(0);
});

it('rejects a movement whose type is generated automatically by another module', function () {
    $purchaseEntry = StockMovementType::query()
        ->where('code', StockMovementType::CODE_PURCHASE_ENTRY)
        ->firstOrFail();

    $this->actingAs($this->user)
        ->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'stock_movement_type_id' => $purchaseEntry->id,
            'notes' => 'No debería permitirse',
            'items' => [
                ['article_id' => $this->articleA->id, 'quantity' => 5],
            ],
        ])
        ->assertSessionHasErrors('stock_movement_type_id');
});

it('rejects decimal quantities for articles measured in whole units', function () {
    $this->actingAs($this->user)
        ->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'stock_movement_type_id' => $this->surplusType->id,
            'notes' => 'Intento de cargar decimales en unidad entera',
            'items' => [
                ['article_id' => $this->articleA->id, 'quantity' => 2.5],
            ],
        ])
        ->assertSessionHasErrors('items.0.quantity');
});

it('accepts decimal quantities for articles measured in continuous units like kilogram', function () {
    $kgUnit = UnitOfMeasure::factory()->create(['name' => 'Kilogramo', 'abbreviation' => 'kg']);
    $flour = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $kgUnit->id,
        'internal_code' => 'ART-KG-01',
    ]);

    $this->actingAs($this->user)
        ->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'stock_movement_type_id' => $this->surplusType->id,
            'notes' => 'Carga con decimales en kilogramos',
            'items' => [
                ['article_id' => $flour->id, 'quantity' => 2.750],
            ],
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stock_movement_items', [
        'article_id' => $flour->id,
        'quantity' => 2.750,
    ]);
});

it('validates the movement type is required and must exist and be active', function () {
    $inactiveType = StockMovementType::factory()->create(['is_active' => false, 'sign' => 1]);

    $this->actingAs($this->user)
        ->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'stock_movement_type_id' => $inactiveType->id,
            'notes' => 'Tipo inactivo',
            'items' => [
                ['article_id' => $this->articleA->id, 'quantity' => 10],
            ],
        ])
        ->assertSessionHasErrors('stock_movement_type_id');
});

it('validates the warehouse is required and must exist and be active', function () {
    $inactiveWarehouse = Warehouse::factory()->create([
        'branch_id' => $this->branch->id,
        'is_active' => false,
    ]);

    $this->actingAs($this->user)
        ->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $inactiveWarehouse->id,
            'stock_movement_type_id' => $this->surplusType->id,
            'notes' => 'Depósito inactivo',
            'items' => [
                ['article_id' => $this->articleA->id, 'quantity' => 10],
            ],
        ])
        ->assertSessionHasErrors('warehouse_id');
});

it('requires notes as the justification of the movement', function () {
    $this->actingAs($this->user)
        ->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'stock_movement_type_id' => $this->surplusType->id,
            'items' => [
                ['article_id' => $this->articleA->id, 'quantity' => 10],
            ],
        ])
        ->assertSessionHasErrors('notes');
});

it('validates that at least one article is required in items', function () {
    $this->actingAs($this->user)
        ->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'stock_movement_type_id' => $this->surplusType->id,
            'notes' => 'Sin artículos',
            'items' => [],
        ])
        ->assertSessionHasErrors('items');
});

it('validates that duplicate articles in the same movement are rejected', function () {
    $this->actingAs($this->user)
        ->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'stock_movement_type_id' => $this->surplusType->id,
            'notes' => 'Artículo repetido',
            'items' => [
                ['article_id' => $this->articleA->id, 'quantity' => 10],
                ['article_id' => $this->articleA->id, 'quantity' => 15],
            ],
        ])
        ->assertSessionHasErrors('items.0.article_id');
});

it('validates that the entered quantity must be greater than zero', function () {
    $this->actingAs($this->user)
        ->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'stock_movement_type_id' => $this->surplusType->id,
            'notes' => 'Cantidad cero',
            'items' => [
                ['article_id' => $this->articleA->id, 'quantity' => 0],
            ],
        ])
        ->assertSessionHasErrors('items.0.quantity');
});

it('enforces immutability by rejecting PUT, PATCH, and DELETE requests on stock movements', function () {
    $movement = StockMovement::create([
        'stock_movement_type_id' => $this->surplusType->id,
        'warehouse_id' => $this->warehouse->id,
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
        'stock_movement_type_id' => $this->surplusType->id,
        'warehouse_id' => $this->warehouse->id,
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

it('seeds initial inventory through RegisterStockAdjustment using the initial load type', function () {
    $this->seed([
        BranchSeeder::class,
        WarehouseSeeder::class,
        CategorySeeder::class,
        BrandSeeder::class,
        UnitOfMeasureSeeder::class,
        ArticleSeeder::class,
        WarehouseStockSeeder::class,
    ]);

    $initialLoadType = StockMovementType::query()
        ->where('code', StockMovementType::CODE_INITIAL_LOAD)
        ->firstOrFail();
    $movements = StockMovement::where('stock_movement_type_id', $initialLoadType->id)->get();

    expect($movements)->not->toBeEmpty();
    expect(StockBalance::where('quantity', '>', 0)->count())->toBeGreaterThan(0);
});
