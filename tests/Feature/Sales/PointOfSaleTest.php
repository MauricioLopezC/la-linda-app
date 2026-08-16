<?php

use App\Models\Inventory\Warehouse;
use App\Models\Sales\PointOfSale;
use App\Models\User;

test('user can create a point of sale for a warehouse', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $response = $this->actingAs($user)->post(route('sales.points-of-sale.store'), [
        'number' => 1,
        'warehouse_id' => $warehouse->id,
        'is_active' => true,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('points_of_sale', [
        'number' => 1,
        'warehouse_id' => $warehouse->id,
    ]);
});

test('user cannot create a point of sale without a warehouse', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('sales.points-of-sale.store'), [
        'number' => 1,
    ]);

    $response->assertSessionHasErrors(['warehouse_id']);
});

test('point of sale number must be unique within the same branch', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    PointOfSale::factory()->create(['number' => 1, 'warehouse_id' => $warehouse->id]);

    $otherWarehouseSameBranch = Warehouse::factory()->create(['branch_id' => $warehouse->branch_id]);

    $response = $this->actingAs($user)->post(route('sales.points-of-sale.store'), [
        'number' => 1,
        'warehouse_id' => $otherWarehouseSameBranch->id,
    ]);

    $response->assertSessionHasErrors(['number']);
});

test('the same point of sale number is allowed in a different branch', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    PointOfSale::factory()->create(['number' => 1, 'warehouse_id' => $warehouse->id]);

    $warehouseOtherBranch = Warehouse::factory()->create();

    $response = $this->actingAs($user)->post(route('sales.points-of-sale.store'), [
        'number' => 1,
        'warehouse_id' => $warehouseOtherBranch->id,
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('points_of_sale', [
        'number' => 1,
        'warehouse_id' => $warehouseOtherBranch->id,
    ]);
});

test('editing a point of sale keeping its own number does not trigger a duplicate error', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $pointOfSale = PointOfSale::factory()->create(['number' => 1, 'warehouse_id' => $warehouse->id]);

    $response = $this->actingAs($user)->put(route('sales.points-of-sale.update', $pointOfSale), [
        'number' => 1,
        'warehouse_id' => $warehouse->id,
        'is_active' => false,
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('points_of_sale', [
        'id' => $pointOfSale->id,
        'is_active' => false,
    ]);
});

test('user can toggle point of sale status', function () {
    $user = User::factory()->create();
    $pointOfSale = PointOfSale::factory()->create(['is_active' => true]);

    $response = $this->actingAs($user)->patch(route('sales.points-of-sale.toggle', $pointOfSale));

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('points_of_sale', [
        'id' => $pointOfSale->id,
        'is_active' => false,
    ]);
});
