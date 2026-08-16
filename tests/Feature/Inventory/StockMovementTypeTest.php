<?php

use App\Models\Inventory\StockMovementType;
use App\Models\User;

test('guests are redirected to login when accessing stock parameters', function () {
    $response = $this->get(route('inventory.parameters.index'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can view stock parameters page with movement types', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('inventory.parameters.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('inventory/parameters/index')
        ->has('movementTypes')
        ->has('adjustmentReasons')
    );
});

test('user can create a custom stock movement type', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('inventory.parameters.movement-types.store'), [
        'name' => 'Consumo interno para exhibición',
        'sign' => -1,
        'description' => 'Salida de mercadería para exhibición en góndola',
        'is_active' => true,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('stock_movement_types', [
        'name' => 'Consumo interno para exhibición',
        'sign' => -1,
        'is_system' => false,
        'is_active' => true,
    ]);
});

test('user cannot create movement type with duplicate name', function () {
    $user = User::factory()->create();
    StockMovementType::factory()->create(['name' => 'Tipo Duplicado']);

    $response = $this->actingAs($user)->post(route('inventory.parameters.movement-types.store'), [
        'name' => 'Tipo Duplicado',
        'sign' => 1,
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('user cannot create movement type with invalid sign', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('inventory.parameters.movement-types.store'), [
        'name' => 'Tipo Invalido',
        'sign' => 5,
    ]);

    $response->assertSessionHasErrors(['sign']);
});

test('user can update a custom movement type', function () {
    $user = User::factory()->create();
    $type = StockMovementType::factory()->create([
        'name' => 'Nombre Viejo',
        'sign' => 1,
        'is_system' => false,
    ]);

    $response = $this->actingAs($user)->put(route('inventory.parameters.movement-types.update', $type), [
        'name' => 'Nombre Nuevo',
        'sign' => -1,
        'description' => 'Descripción actualizada',
        'is_active' => true,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('stock_movement_types', [
        'id' => $type->id,
        'name' => 'Nombre Nuevo',
        'sign' => -1,
        'description' => 'Descripción actualizada',
    ]);
});

test('system movement types cannot be deleted', function () {
    $user = User::factory()->create();
    $systemType = StockMovementType::factory()->system()->create([
        'name' => 'Entrada por compra protegida',
        'code' => 'system_purchase_entry',
        'sign' => 1,
    ]);

    $response = $this->actingAs($user)->delete(route('inventory.parameters.movement-types.destroy', $systemType));

    $response->assertSessionHasErrors(['movement_type']);
    $this->assertDatabaseHas('stock_movement_types', ['id' => $systemType->id]);
});

test('custom movement type can be deleted', function () {
    $user = User::factory()->create();
    $customType = StockMovementType::factory()->create([
        'name' => 'Tipo Borrable',
        'is_system' => false,
    ]);

    $response = $this->actingAs($user)->delete(route('inventory.parameters.movement-types.destroy', $customType));

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('stock_movement_types', ['id' => $customType->id]);
});
