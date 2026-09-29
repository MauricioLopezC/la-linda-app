<?php

use App\Models\Catalog\Article;
use App\Models\Catalog\UnitOfMeasure;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementItem;
use App\Models\Inventory\StockMovementType;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('guest cannot access movement history', function () {
    $this->get(route('inventory.movements.index'))->assertRedirect(route('login'));
});

test('user can view movement history page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('inventory.movements.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/movements/index')
            ->has('movements.data', 0)
            ->has('warehouses')
            ->has('movementTypes')
            ->has('users')
            ->has('filters')
        );
});

test('movements are listed with correct data', function () {
    $user = User::factory()->create(['name' => 'John Doe']);
    $warehouse = Warehouse::factory()->create(['name' => 'Depósito Central']);
    $unit = UnitOfMeasure::factory()->create(['name' => 'Unidad']);
    $article1 = Article::factory()->create(['description' => 'Producto A', 'unit_of_measure_id' => $unit->id]);
    $article2 = Article::factory()->create(['description' => 'Producto B', 'unit_of_measure_id' => $unit->id]);

    $type = StockMovementType::firstOrCreate(
        ['code' => 'count_surplus'],
        ['name' => 'Ajuste por sobrante de recuento', 'sign' => 1, 'is_system' => true, 'is_active' => true]
    );

    $movement = StockMovement::factory()->create([
        'user_id' => $user->id,
        'warehouse_id' => $warehouse->id,
        'stock_movement_type_id' => $type->id,
        'created_at' => Carbon::parse('2024-01-01 10:00:00'),
    ]);

    StockMovementItem::factory()->create(['stock_movement_id' => $movement->id, 'article_id' => $article1->id, 'quantity' => 10.5]);
    StockMovementItem::factory()->create(['stock_movement_id' => $movement->id, 'article_id' => $article2->id, 'quantity' => -5]);

    $response = $this->actingAs($user)->get(route('inventory.movements.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $movement->id)
            ->where('movements.data.0.type_name', 'Ajuste por sobrante de recuento')
            ->where('movements.data.0.warehouse_name', 'Depósito Central')
            ->where('movements.data.0.user_name', 'John Doe')
            ->where('movements.data.0.items_count', 2)
            ->where('movements.data.0.total_quantity', '15.500')
        );
});

test('movements are paginated', function () {
    $user = User::factory()->create();

    StockMovement::factory()->count(30)->create();

    $response = $this->actingAs($user)->get(route('inventory.movements.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('movements.data', 25)
            ->where('movements.total', 30)
        );
});

test('filter by warehouse', function () {
    $user = User::factory()->create();
    $wh1 = Warehouse::factory()->create();
    $wh2 = Warehouse::factory()->create();

    StockMovement::factory()->create(['warehouse_id' => $wh1->id]);
    StockMovement::factory()->create(['warehouse_id' => $wh2->id]);

    $response = $this->actingAs($user)->get(route('inventory.movements.index', ['warehouse_id' => $wh1->id]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('movements.data', 1)
            ->where('movements.data.0.warehouse_name', $wh1->name)
        );
});

test('filter by movement type', function () {
    $user = User::factory()->create();
    $type1 = StockMovementType::factory()->create();
    $type2 = StockMovementType::factory()->create();

    StockMovement::factory()->create(['stock_movement_type_id' => $type1->id]);
    StockMovement::factory()->create(['stock_movement_type_id' => $type2->id]);

    $response = $this->actingAs($user)->get(route('inventory.movements.index', ['stock_movement_type_id' => $type1->id]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('movements.data', 1)
            ->where('movements.data.0.type_name', $type1->name)
        );
});

test('filter by user', function () {
    $user = User::factory()->create();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    StockMovement::factory()->create(['user_id' => $user1->id]);
    StockMovement::factory()->create(['user_id' => $user2->id]);

    $response = $this->actingAs($user)->get(route('inventory.movements.index', ['user_id' => $user1->id]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('movements.data', 1)
            ->where('movements.data.0.user_name', $user1->name)
        );
});

test('filter by date range', function () {
    $user = User::factory()->create();

    StockMovement::factory()->create(['created_at' => Carbon::parse('2024-01-01')]);
    $targetMovement = StockMovement::factory()->create(['created_at' => Carbon::parse('2024-01-15')]);
    StockMovement::factory()->create(['created_at' => Carbon::parse('2024-02-01')]);

    $response = $this->actingAs($user)->get(route('inventory.movements.index', [
        'date_from' => '2024-01-10',
        'date_to' => '2024-01-20',
    ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $targetMovement->id)
        );
});

test('filter by article search', function () {
    $user = User::factory()->create();

    $article1 = Article::factory()->create(['internal_code' => 'ART123', 'description' => 'Fideos']);
    $article2 = Article::factory()->create(['internal_code' => 'ART456', 'description' => 'Arroz']);

    $mov1 = StockMovement::factory()->create();
    StockMovementItem::factory()->create(['stock_movement_id' => $mov1->id, 'article_id' => $article1->id]);

    $mov2 = StockMovement::factory()->create();
    StockMovementItem::factory()->create(['stock_movement_id' => $mov2->id, 'article_id' => $article2->id]);

    // Search by code
    $response1 = $this->actingAs($user)->get(route('inventory.movements.index', ['search' => '123']));
    $response1->assertOk()->assertInertia(fn (Assert $page) => $page->has('movements.data', 1)->where('movements.data.0.id', $mov1->id));

    // Search by description
    $response2 = $this->actingAs($user)->get(route('inventory.movements.index', ['search' => 'roz']));
    $response2->assertOk()->assertInertia(fn (Assert $page) => $page->has('movements.data', 1)->where('movements.data.0.id', $mov2->id));
});

test('filter by article id lists the movements that touched the article', function () {
    $user = User::factory()->create();
    $article = Article::factory()->create();
    $otherArticle = Article::factory()->create();

    $targetMovement = StockMovement::factory()->create();
    StockMovementItem::factory()->create(['stock_movement_id' => $targetMovement->id, 'article_id' => $article->id]);

    $otherMovement = StockMovement::factory()->create();
    StockMovementItem::factory()->create(['stock_movement_id' => $otherMovement->id, 'article_id' => $otherArticle->id]);

    $this->actingAs($user)->get(route('inventory.movements.index', ['article_id' => $article->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('movements.data', 1)
            ->where('movements.data.0.id', $targetMovement->id)
            ->where('article.id', $article->id)
            ->where('kardex', null)
        );
});

test('article and warehouse together show the kardex with its running balance', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $otherWarehouse = Warehouse::factory()->create();
    $article = Article::factory()->create();
    $otherArticle = Article::factory()->create();

    $recordMovement = function (Warehouse $warehouse, Article $article, string $quantity, string $date): StockMovement {
        $movement = StockMovement::factory()->create([
            'warehouse_id' => $warehouse->id,
            'created_at' => Carbon::parse($date),
        ]);
        StockMovementItem::factory()->create([
            'stock_movement_id' => $movement->id,
            'article_id' => $article->id,
            'quantity' => $quantity,
        ]);

        return $movement;
    };

    $recordMovement($warehouse, $article, '100', '2026-09-01 09:00:00');
    $recordMovement($warehouse, $article, '-2', '2026-09-15 10:00:00');
    $recordMovement($otherWarehouse, $article, '50', '2026-09-16 10:00:00');
    $recordMovement($warehouse, $otherArticle, '7', '2026-09-17 10:00:00');
    $purchase = $recordMovement($warehouse, $article, '240', '2026-09-29 15:00:00');

    StockBalance::factory()->create([
        'article_id' => $article->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '338',
    ]);

    $this->actingAs($user)->get(route('inventory.movements.index', [
        'article_id' => $article->id,
        'warehouse_id' => $warehouse->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('movements', null)
            ->where('currentBalance', '338.000')
            ->has('kardex.data', 3)
            ->where('kardex.data.0.stock_movement_id', $purchase->id)
            ->where('kardex.data.0.quantity', '240.000')
            ->where('kardex.data.0.balance', '338.000')
            ->where('kardex.data.1.quantity', '-2.000')
            ->where('kardex.data.1.balance', '98.000')
            ->where('kardex.data.2.balance', '100.000')
        );

    // A date filter narrows the rows but keeps the real balance at each point in time.
    $this->actingAs($user)->get(route('inventory.movements.index', [
        'article_id' => $article->id,
        'warehouse_id' => $warehouse->id,
        'date_from' => '2026-09-20',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('kardex.data', 1)
            ->where('kardex.data.0.balance', '338.000')
        );
});

test('article search for the history filter includes inactive articles', function () {
    $user = User::factory()->create();
    $active = Article::factory()->create(['internal_code' => 'KDX-001', 'description' => 'Duraznos en almíbar']);
    $inactive = Article::factory()->inactive()->create(['internal_code' => 'KDX-002', 'description' => 'Duraznos light']);
    Article::factory()->create(['internal_code' => 'OTR-001', 'description' => 'Arroz']);

    $this->actingAs($user)
        ->getJson(route('inventory.movements.articles', ['search' => 'durazn']))
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonFragment(['id' => $active->id, 'internal_code' => 'KDX-001'])
        ->assertJsonFragment(['id' => $inactive->id, 'internal_code' => 'KDX-002']);
});

test('article search for the history filter requires at least two characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('inventory.movements.articles', ['search' => 'd']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('search');
});

test('filters combine', function () {
    $user = User::factory()->create();
    $wh1 = Warehouse::factory()->create();
    $wh2 = Warehouse::factory()->create();
    $user1 = User::factory()->create();

    StockMovement::factory()->create(['warehouse_id' => $wh1->id, 'user_id' => $user1->id]);
    StockMovement::factory()->create(['warehouse_id' => $wh2->id, 'user_id' => $user1->id]); // Different warehouse

    $response = $this->actingAs($user)->get(route('inventory.movements.index', [
        'warehouse_id' => $wh1->id,
        'user_id' => $user1->id,
    ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('movements.data', 1)
        );
});

test('no edit or delete routes exist for movements', function () {
    $user = User::factory()->create();
    $movement = StockMovement::factory()->create();

    // Since they don't exist in web.php, they should 405 (Method Not Allowed) or 404 (Not Found)
    // For route('inventory.movements.index') it's a GET, so a POST/PUT/DELETE will hit the same URL
    // but the route is defined strictly as Route::get('/').
    // Let's assert that a POST to /inventory/movements gives 405 Method Not Allowed.

    $this->actingAs($user)->post('/inventory/movements')->assertStatus(405);
    $this->actingAs($user)->put('/inventory/movements/'.$movement->id)->assertStatus(404);
    $this->actingAs($user)->delete('/inventory/movements/'.$movement->id)->assertStatus(404);
});
