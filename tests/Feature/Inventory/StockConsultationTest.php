<?php

use App\Models\Catalog\Article;
use App\Models\Catalog\Category;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseStock;
use App\Models\Organization\Branch;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guest cannot access stock balances consultation', function () {
    $this->get(route('inventory.stocks.index'))->assertRedirect(route('login'));
    $this->get(route('inventory.stocks.export'))->assertRedirect(route('login'));
});

test('user can view stock consultation with all warehouses across system', function () {
    $user = User::factory()->create();

    $branch1 = Branch::factory()->create(['name' => 'Sucursal Centro']);
    $branch2 = Branch::factory()->create(['name' => 'Sucursal Norte']);

    $wh1 = Warehouse::factory()->create(['branch_id' => $branch1->id, 'name' => 'Depósito Centro']);
    $wh2 = Warehouse::factory()->create(['branch_id' => $branch2->id, 'name' => 'Depósito Norte']);

    $article1 = Article::factory()->create(['internal_code' => 'ART-001', 'description' => 'Arroz 1kg']);
    $article2 = Article::factory()->create(['internal_code' => 'ART-002', 'description' => 'Fideos 500g']);

    WarehouseStock::factory()->create([
        'article_id' => $article1->id,
        'warehouse_id' => $wh1->id,
        'quantity' => 100,
        'min_stock' => 20,
    ]);

    WarehouseStock::factory()->create([
        'article_id' => $article2->id,
        'warehouse_id' => $wh2->id,
        'quantity' => 50,
        'min_stock' => 10,
    ]);

    $response = $this->actingAs($user)->get(route('inventory.stocks.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/stocks/index')
            ->has('stocks', 2)
            ->has('categories')
            ->has('warehouses', 2)
            ->where('totals.grand_total_quantity', 150)
            ->where('totals.grand_total_items', 2)
            ->where('totals.total_low_stock', 0)
            ->where('totals.total_out_of_stock', 0)
            ->has('totals.branch_totals', 2)
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

    WarehouseStock::factory()->create([
        'article_id' => $article1->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 40,
    ]);
    WarehouseStock::factory()->create([
        'article_id' => $article2->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 20,
    ]);

    // Search by description
    $response = $this->actingAs($user)->get(route('inventory.stocks.index', ['search' => 'Yerba']));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('stocks', 1)
        ->where('stocks.0.article_code', 'ART-SEARCH-1')
    );

    // Search by internal code
    $response = $this->actingAs($user)->get(route('inventory.stocks.index', ['search' => 'SEARCH-1']));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('stocks', 1)
        ->where('stocks.0.article_description', 'Yerba Mate Especial 1kg')
    );

    // Search by barcode
    $response = $this->actingAs($user)->get(route('inventory.stocks.index', ['search' => '7791234567890']));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('stocks', 1)
        ->where('stocks.0.article_code', 'ART-SEARCH-1')
    );
});

test('user can filter stock by category', function () {
    $user = User::factory()->create();

    $categoryA = Category::factory()->create(['name' => 'Bebidas']);
    $categoryB = Category::factory()->create(['name' => 'Lácteos']);

    $articleA = Article::factory()->create(['category_id' => $categoryA->id]);
    $articleB = Article::factory()->create(['category_id' => $categoryB->id]);

    $warehouse = Warehouse::factory()->create();

    WarehouseStock::factory()->create(['article_id' => $articleA->id, 'warehouse_id' => $warehouse->id]);
    WarehouseStock::factory()->create(['article_id' => $articleB->id, 'warehouse_id' => $warehouse->id]);

    $response = $this->actingAs($user)->get(route('inventory.stocks.index', ['category_id' => $categoryA->id]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('stocks', 1)
        ->where('stocks.0.category_name', 'Bebidas')
    );
});

test('user can filter stock by warehouse', function () {
    $user = User::factory()->create();

    $wh1 = Warehouse::factory()->create(['name' => 'Depósito 1']);
    $wh2 = Warehouse::factory()->create(['name' => 'Depósito 2']);

    $article = Article::factory()->create();

    WarehouseStock::factory()->create(['article_id' => $article->id, 'warehouse_id' => $wh1->id]);
    WarehouseStock::factory()->create(['article_id' => $article->id, 'warehouse_id' => $wh2->id]);

    $response = $this->actingAs($user)->get(route('inventory.stocks.index', ['warehouse_id' => $wh1->id]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('stocks', 1)
        ->where('stocks.0.warehouse_name', 'Depósito 1')
    );
});

test('user can filter stock by stock status', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $inStockArticle = Article::factory()->create(['internal_code' => 'ART-IN']);
    $belowMinArticle = Article::factory()->create(['internal_code' => 'ART-LOW']);
    $outOfStockArticle = Article::factory()->create(['internal_code' => 'ART-OUT']);

    // In stock (> min_stock)
    WarehouseStock::factory()->create([
        'article_id' => $inStockArticle->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 50,
        'min_stock' => 10,
    ]);

    // Below minimum (> 0 and <= min_stock)
    WarehouseStock::factory()->create([
        'article_id' => $belowMinArticle->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
        'min_stock' => 10,
    ]);

    // Out of stock (0)
    WarehouseStock::factory()->create([
        'article_id' => $outOfStockArticle->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 0,
        'min_stock' => 10,
    ]);

    // Filter in_stock
    $responseIn = $this->actingAs($user)->get(route('inventory.stocks.index', ['status' => 'in_stock']));
    $responseIn->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('stocks', 2) // quantity > 0 (both 50 and 5)
    );

    // Filter below_min
    $responseLow = $this->actingAs($user)->get(route('inventory.stocks.index', ['status' => 'below_min']));
    $responseLow->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('stocks', 1)
        ->where('stocks.0.article_code', 'ART-LOW')
    );

    // Filter out_of_stock
    $responseOut = $this->actingAs($user)->get(route('inventory.stocks.index', ['status' => 'out_of_stock']));
    $responseOut->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('stocks', 1)
        ->where('stocks.0.article_code', 'ART-OUT')
    );
});

test('branch totals accurately aggregate warehouse stock quantities and alerts', function () {
    $user = User::factory()->create();

    $branchA = Branch::factory()->create(['name' => 'Sucursal A']);
    $branchB = Branch::factory()->create(['name' => 'Sucursal B']);

    $whA1 = Warehouse::factory()->create(['branch_id' => $branchA->id]);
    $whA2 = Warehouse::factory()->create(['branch_id' => $branchA->id]);
    $whB1 = Warehouse::factory()->create(['branch_id' => $branchB->id]);

    $art1 = Article::factory()->create();
    $art2 = Article::factory()->create();

    // Branch A Wh 1: 100 in stock
    WarehouseStock::factory()->create([
        'article_id' => $art1->id,
        'warehouse_id' => $whA1->id,
        'quantity' => 100,
        'min_stock' => 10,
    ]);
    // Branch A Wh 2: 5 low stock
    WarehouseStock::factory()->create([
        'article_id' => $art2->id,
        'warehouse_id' => $whA2->id,
        'quantity' => 5,
        'min_stock' => 10,
    ]);
    // Branch B Wh 1: 0 out of stock
    WarehouseStock::factory()->create([
        'article_id' => $art1->id,
        'warehouse_id' => $whB1->id,
        'quantity' => 0,
        'min_stock' => 10,
    ]);

    $response = $this->actingAs($user)->get(route('inventory.stocks.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('totals.grand_total_quantity', 105)
        ->where('totals.grand_total_items', 3)
        ->where('totals.total_low_stock', 1)
        ->where('totals.total_out_of_stock', 1)
        ->has('totals.branch_totals', 2)
        ->where('totals.branch_totals.0.total_quantity', 105)
        ->where('totals.branch_totals.0.low_stock_count', 1)
        ->where('totals.branch_totals.1.total_quantity', 0)
        ->where('totals.branch_totals.1.out_of_stock_count', 1)
    );
});

test('user can export stock balances to CSV with UTF-8 BOM and headers', function () {
    $user = User::factory()->create();
    $branch = Branch::factory()->create(['name' => 'Sucursal Central']);
    $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id, 'name' => 'Depósito Principal']);
    $category = Category::factory()->create(['name' => 'Conservas']);
    $article = Article::factory()->create([
        'internal_code' => 'EXP-001',
        'description' => 'Tomates Pelados 400g',
        'category_id' => $category->id,
    ]);

    WarehouseStock::factory()->create([
        'article_id' => $article->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 75.50,
        'min_stock' => 15.00,
    ]);

    $response = $this->actingAs($user)->get(route('inventory.stocks.export'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();

    // Contains UTF-8 BOM
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue();

    // Contains Header Row
    expect($content)->toContain('Código;Artículo;Categoría;Marca;"Unidad de Medida";Sucursal;Depósito;Existencia;"Stock Mínimo";Estado');

    // Contains Data Row
    expect($content)->toContain('EXP-001;"Tomates Pelados 400g";Conservas');
    expect($content)->toContain('"Sucursal Central";"Depósito Principal";75,50;15,00;"En stock"');
});

test('warehouse cannot be deactivated when it has registered stock', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    $article = Article::factory()->create();

    WarehouseStock::factory()->create([
        'article_id' => $article->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
    ]);

    $response = $this->actingAs($user)->patch(route('inventory.warehouses.toggle', $warehouse));

    $response->assertSessionHasErrors(['warehouse']);
    expect($warehouse->fresh()->is_active)->toBeTrue();
});
