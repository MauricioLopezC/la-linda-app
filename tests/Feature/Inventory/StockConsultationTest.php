<?php

use App\Models\Catalog\Article;
use App\Models\Catalog\Category;
use App\Models\Catalog\UnitOfMeasure;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guest cannot access stock balances consultation', function () {
    $this->get(route('inventory.stocks.index'))->assertRedirect(route('login'));
});

test('user can view stock consultation with all warehouses across system', function () {
    $user = User::factory()->create();

    $unit = UnitOfMeasure::factory()->create(['name' => 'Unidad', 'abbreviation' => 'u']);

    $branch1 = Branch::factory()->create(['name' => 'Sucursal Centro']);
    $branch2 = Branch::factory()->create(['name' => 'Sucursal Norte']);

    $wh1 = Warehouse::factory()->create(['branch_id' => $branch1->id, 'name' => 'Depósito Centro']);
    $wh2 = Warehouse::factory()->create(['branch_id' => $branch2->id, 'name' => 'Depósito Norte']);

    $article1 = Article::factory()->create([
        'internal_code' => 'ART-001',
        'description' => 'Arroz 1kg',
        'unit_of_measure_id' => $unit->id,
    ]);
    $article2 = Article::factory()->create([
        'internal_code' => 'ART-002',
        'description' => 'Fideos 500g',
        'unit_of_measure_id' => $unit->id,
    ]);

    StockBalance::factory()->create([
        'article_id' => $article1->id,
        'warehouse_id' => $wh1->id,
        'quantity' => 100,
    ]);

    StockBalance::factory()->create([
        'article_id' => $article2->id,
        'warehouse_id' => $wh2->id,
        'quantity' => 50,
    ]);

    $response = $this->actingAs($user)->get(route('inventory.stocks.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/stocks/index')
            ->has('stocks.data', 2)
            ->has('categories')
            ->has('warehouses', 2)
            ->where('totals.grand_total_items', 2)
            ->where('totals.total_in_stock', 2)
            ->where('totals.total_out_of_stock', 0)
            ->has('totals.branch_totals', 2)
            ->where('totals.branch_totals.0.total_items', 1)
            ->where('totals.branch_totals.0.in_stock_count', 1)
            ->where('totals.branch_totals.0.out_of_stock_count', 0)
            ->where('totals.branch_totals.1.total_items', 1)
            ->where('totals.branch_totals.1.in_stock_count', 1)
            ->where('totals.branch_totals.1.out_of_stock_count', 0)
        );
});

test('totals accurately calculate in stock and out of stock metrics for articles in same warehouse', function () {
    $user = User::factory()->create();

    $unitUnits = UnitOfMeasure::factory()->create(['name' => 'Unidad', 'abbreviation' => 'u']);
    $unitKg = UnitOfMeasure::factory()->create(['name' => 'Kilogramo', 'abbreviation' => 'kg']);

    $branch = Branch::factory()->create(['name' => 'Sucursal Principal']);
    $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id, 'name' => 'Depósito Central']);

    $cannedArticle = Article::factory()->create([
        'internal_code' => 'LATA-01',
        'description' => 'Atún en lata 170g',
        'unit_of_measure_id' => $unitUnits->id,
    ]);

    $bulkArticle = Article::factory()->create([
        'internal_code' => 'HAR-01',
        'description' => 'Harina 000 a granel',
        'unit_of_measure_id' => $unitKg->id,
    ]);

    $outArticle = Article::factory()->create([
        'internal_code' => 'ACE-01',
        'description' => 'Aceite Girasol 1.5L',
    ]);

    // 2 articles with stock, 1 out of stock
    StockBalance::factory()->create([
        'article_id' => $cannedArticle->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 120.000,
    ]);

    StockBalance::factory()->create([
        'article_id' => $bulkArticle->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 200.500,
    ]);

    StockBalance::factory()->create([
        'article_id' => $outArticle->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 0.000,
    ]);

    $response = $this->actingAs($user)->get(route('inventory.stocks.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('stocks.data', 3)
            ->where('totals.grand_total_items', 3)
            ->where('totals.total_in_stock', 2)
            ->where('totals.total_out_of_stock', 1)
            ->has('totals.branch_totals', 1)
            ->where('totals.branch_totals.0.total_items', 3)
            ->where('totals.branch_totals.0.in_stock_count', 2)
            ->where('totals.branch_totals.0.out_of_stock_count', 1)
        );
});

test('user can filter stock by article code, description or barcode', function () {
    $user = User::factory()->create();

    $article1 = Article::factory()->create([
        'internal_code' => 'ART-SEARCH-1',
        'description' => 'Yerba Mate Especial 1kg',
        'barcode' => '7791234567890',
    ]);
    $article2 = Article::factory()->create([
        'internal_code' => 'ART-OTHER-2',
        'description' => 'Café Tostado 250g',
        'barcode' => '7799876543210',
    ]);

    $warehouse = Warehouse::factory()->create();

    StockBalance::factory()->create([
        'article_id' => $article1->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 40,
    ]);
    StockBalance::factory()->create([
        'article_id' => $article2->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 20,
    ]);

    // Search by description
    $response = $this->actingAs($user)->get(route('inventory.stocks.index', ['search' => 'Yerba']));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('stocks.data', 1)
        ->where('stocks.data.0.article_code', 'ART-SEARCH-1')
    );

    // Search by internal code
    $response = $this->actingAs($user)->get(route('inventory.stocks.index', ['search' => 'SEARCH-1']));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('stocks.data', 1)
        ->where('stocks.data.0.article_description', 'Yerba Mate Especial 1kg')
    );

    // Search by barcode
    $response = $this->actingAs($user)->get(route('inventory.stocks.index', ['search' => '7791234567890']));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('stocks.data', 1)
        ->where('stocks.data.0.article_code', 'ART-SEARCH-1')
    );
});

test('user can filter stock by category', function () {
    $user = User::factory()->create();

    $categoryA = Category::factory()->create(['name' => 'Bebidas']);
    $categoryB = Category::factory()->create(['name' => 'Lácteos']);

    $articleA = Article::factory()->create(['category_id' => $categoryA->id]);
    $articleB = Article::factory()->create(['category_id' => $categoryB->id]);

    $warehouse = Warehouse::factory()->create();

    StockBalance::factory()->create(['article_id' => $articleA->id, 'warehouse_id' => $warehouse->id]);
    StockBalance::factory()->create(['article_id' => $articleB->id, 'warehouse_id' => $warehouse->id]);

    $response = $this->actingAs($user)->get(route('inventory.stocks.index', ['category_id' => $categoryA->id]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('stocks.data', 1)
        ->where('stocks.data.0.category_name', 'Bebidas')
    );
});

test('user can filter stock by warehouse', function () {
    $user = User::factory()->create();

    $wh1 = Warehouse::factory()->create(['name' => 'Depósito 1']);
    $wh2 = Warehouse::factory()->create(['name' => 'Depósito 2']);

    $article = Article::factory()->create();

    StockBalance::factory()->create(['article_id' => $article->id, 'warehouse_id' => $wh1->id]);
    StockBalance::factory()->create(['article_id' => $article->id, 'warehouse_id' => $wh2->id]);

    $response = $this->actingAs($user)->get(route('inventory.stocks.index', ['warehouse_id' => $wh1->id]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('stocks.data', 1)
        ->where('stocks.data.0.warehouse_name', 'Depósito 1')
    );
});

test('user can filter stock by stock status', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $inStockArticle = Article::factory()->create(['internal_code' => 'ART-IN']);
    $outOfStockArticle = Article::factory()->create(['internal_code' => 'ART-OUT']);

    // In stock (quantity > 0)
    StockBalance::factory()->create([
        'article_id' => $inStockArticle->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 50,
    ]);

    // Out of stock (quantity = 0)
    StockBalance::factory()->create([
        'article_id' => $outOfStockArticle->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 0,
    ]);

    // Filter in_stock
    $responseIn = $this->actingAs($user)->get(route('inventory.stocks.index', ['status' => 'in_stock']));
    $responseIn->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('stocks.data', 1)
        ->where('stocks.data.0.article_code', 'ART-IN')
    );

    // Filter out_of_stock
    $responseOut = $this->actingAs($user)->get(route('inventory.stocks.index', ['status' => 'out_of_stock']));
    $responseOut->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('stocks.data', 1)
        ->where('stocks.data.0.article_code', 'ART-OUT')
    );
});

test('branch totals accurately aggregate warehouse stock quantities and alerts', function () {
    $user = User::factory()->create();

    $unit = UnitOfMeasure::factory()->create(['name' => 'Unidad', 'abbreviation' => 'u']);

    $branchA = Branch::factory()->create(['name' => 'Sucursal A']);
    $branchB = Branch::factory()->create(['name' => 'Sucursal B']);

    $whA1 = Warehouse::factory()->create(['branch_id' => $branchA->id]);
    $whA2 = Warehouse::factory()->create(['branch_id' => $branchA->id]);
    $whB1 = Warehouse::factory()->create(['branch_id' => $branchB->id]);

    $art1 = Article::factory()->create(['unit_of_measure_id' => $unit->id]);
    $art2 = Article::factory()->create(['unit_of_measure_id' => $unit->id]);

    // Branch A Wh 1: 100 in stock
    StockBalance::factory()->create([
        'article_id' => $art1->id,
        'warehouse_id' => $whA1->id,
        'quantity' => 100,
    ]);
    // Branch A Wh 2: 5 in stock
    StockBalance::factory()->create([
        'article_id' => $art2->id,
        'warehouse_id' => $whA2->id,
        'quantity' => 5,
    ]);
    // Branch B Wh 1: 0 out of stock
    StockBalance::factory()->create([
        'article_id' => $art1->id,
        'warehouse_id' => $whB1->id,
        'quantity' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('inventory.stocks.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('totals.grand_total_items', 3)
        ->where('totals.total_in_stock', 2)
        ->where('totals.total_out_of_stock', 1)
        ->has('totals.branch_totals', 2)
        ->where('totals.branch_totals.0.total_items', 2)
        ->where('totals.branch_totals.0.in_stock_count', 2)
        ->where('totals.branch_totals.0.out_of_stock_count', 0)
        ->where('totals.branch_totals.1.total_items', 1)
        ->where('totals.branch_totals.1.in_stock_count', 0)
        ->where('totals.branch_totals.1.out_of_stock_count', 1)
    );
});

test('stock list is paginated while totals still reflect every matching row', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockBalance::factory()
        ->count(30)
        ->sequence(fn ($sequence) => [
            'article_id' => Article::factory()->create([
                'internal_code' => sprintf('ART-PAG-%02d', $sequence->index),
            ])->id,
        ])
        ->create([
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
        ]);

    $firstPage = $this->actingAs($user)->get(route('inventory.stocks.index'));

    $firstPage->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('stocks.data', 25)
        ->where('stocks.total', 30)
        ->where('stocks.current_page', 1)
        ->where('stocks.last_page', 2)
        ->where('totals.grand_total_items', 30)
        ->where('totals.total_in_stock', 30)
        ->where('totals.branch_totals.0.total_items', 30)
    );

    $secondPage = $this->actingAs($user)->get(route('inventory.stocks.index', ['page' => 2]));

    $secondPage->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('stocks.data', 5)
        ->where('stocks.current_page', 2)
    );
});

test('warehouse cannot be deactivated when it has registered stock', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    $article = Article::factory()->create();

    StockBalance::factory()->create([
        'article_id' => $article->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
    ]);

    $response = $this->actingAs($user)->patch(route('inventory.warehouses.toggle', $warehouse));

    $response->assertSessionHasErrors(['warehouse']);
    expect($warehouse->fresh()->is_active)->toBeTrue();
});
