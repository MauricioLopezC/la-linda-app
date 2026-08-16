<?php

use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\User;

test('user can create a warehouse for a branch', function () {
    $user = User::factory()->create();
    $branch = Branch::factory()->create();

    $response = $this->actingAs($user)->post(route('inventory.warehouses.store'), [
        'name' => 'Depósito Central',
        'branch_id' => $branch->id,
        'is_online_channel' => false,
        'is_active' => true,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('warehouses', [
        'name' => 'Depósito Central',
        'branch_id' => $branch->id,
    ]);
});

test('user cannot create a warehouse without a branch', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('inventory.warehouses.store'), [
        'name' => 'Depósito Sin Sucursal',
    ]);

    $response->assertSessionHasErrors(['branch_id']);
});

test('at most one warehouse can be marked as online channel', function () {
    $user = User::factory()->create();
    $branch = Branch::factory()->create();
    Warehouse::factory()->online()->create(['branch_id' => $branch->id]);

    $response = $this->actingAs($user)->post(route('inventory.warehouses.store'), [
        'name' => 'Segundo Depósito Online',
        'branch_id' => $branch->id,
        'is_online_channel' => true,
    ]);

    $response->assertSessionHasErrors(['is_online_channel']);
});

test('editing the current online warehouse does not trigger the online channel error', function () {
    $user = User::factory()->create();
    $branch = Branch::factory()->create();
    $warehouse = Warehouse::factory()->online()->create(['branch_id' => $branch->id]);

    $response = $this->actingAs($user)->put(route('inventory.warehouses.update', $warehouse), [
        'name' => 'Depósito Online Renombrado',
        'branch_id' => $branch->id,
        'is_online_channel' => true,
    ]);

    $response->assertSessionHasNoErrors();
});

test('user can toggle warehouse status when it has no registered stock', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['is_active' => true]);

    $response = $this->actingAs($user)->patch(route('inventory.warehouses.toggle', $warehouse));

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('warehouses', [
        'id' => $warehouse->id,
        'is_active' => false,
    ]);
});
