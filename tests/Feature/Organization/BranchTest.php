<?php

use App\Models\Organization\Branch;
use App\Models\User;

test('user can create a branch', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('organization.branches.store'), [
        'name' => 'Sucursal Centro',
        'address' => 'Av. San Martín 1250',
        'phone' => '387-4001100',
        'is_active' => true,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('branches', [
        'name' => 'Sucursal Centro',
        'address' => 'Av. San Martín 1250',
        'phone' => '387-4001100',
        'is_active' => true,
    ]);
});

test('user cannot create a branch with duplicate name', function () {
    $user = User::factory()->create();
    Branch::factory()->create(['name' => 'Sucursal Existente']);

    $response = $this->actingAs($user)->post(route('organization.branches.store'), [
        'name' => 'Sucursal Existente',
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('user can update a branch', function () {
    $user = User::factory()->create();
    $branch = Branch::factory()->create(['name' => 'Sucursal Vieja']);

    $response = $this->actingAs($user)->put(route('organization.branches.update', $branch), [
        'name' => 'Sucursal Editada',
        'address' => 'Nueva dirección',
        'phone' => '387-0000000',
        'is_active' => false,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('branches', [
        'id' => $branch->id,
        'name' => 'Sucursal Editada',
        'is_active' => false,
    ]);
});

test('user can toggle branch status when it has no registered stock', function () {
    $user = User::factory()->create();
    $branch = Branch::factory()->create(['is_active' => true]);

    $response = $this->actingAs($user)->patch(route('organization.branches.toggle', $branch));

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('branches', [
        'id' => $branch->id,
        'is_active' => false,
    ]);

    $response = $this->actingAs($user)->patch(route('organization.branches.toggle', $branch));

    $this->assertDatabaseHas('branches', [
        'id' => $branch->id,
        'is_active' => true,
    ]);
});
