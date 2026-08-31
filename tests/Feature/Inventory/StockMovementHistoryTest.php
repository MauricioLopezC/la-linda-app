<?php

use App\Models\Catalog\Article;
use App\Models\Catalog\UnitOfMeasure;
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
