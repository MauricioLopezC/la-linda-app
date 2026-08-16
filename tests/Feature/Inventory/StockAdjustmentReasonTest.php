<?php

use App\Models\Inventory\StockAdjustmentReason;
use App\Models\User;

test('user can create a stock adjustment reason', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('inventory.parameters.adjustment-reasons.store'), [
        'name' => 'Mercadería caída en pasillo',
        'description' => 'Rotura accidental por cliente o personal',
        'is_active' => true,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('stock_adjustment_reasons', [
        'name' => 'Mercadería caída en pasillo',
        'description' => 'Rotura accidental por cliente o personal',
        'is_active' => true,
    ]);
});

test('user cannot create adjustment reason with duplicate name', function () {
    $user = User::factory()->create();
    StockAdjustmentReason::factory()->create(['name' => 'Motivo Existente']);

    $response = $this->actingAs($user)->post(route('inventory.parameters.adjustment-reasons.store'), [
        'name' => 'Motivo Existente',
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('user can update adjustment reason', function () {
    $user = User::factory()->create();
    $reason = StockAdjustmentReason::factory()->create(['name' => 'Motivo Antiguo']);

    $response = $this->actingAs($user)->put(route('inventory.parameters.adjustment-reasons.update', $reason), [
        'name' => 'Motivo Editado',
        'description' => 'Nueva descripción',
        'is_active' => false,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('stock_adjustment_reasons', [
        'id' => $reason->id,
        'name' => 'Motivo Editado',
        'description' => 'Nueva descripción',
        'is_active' => false,
    ]);
});

test('user can toggle adjustment reason status', function () {
    $user = User::factory()->create();
    $reason = StockAdjustmentReason::factory()->create(['is_active' => true]);

    $response = $this->actingAs($user)->patch(route('inventory.parameters.adjustment-reasons.toggle', $reason));

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('stock_adjustment_reasons', [
        'id' => $reason->id,
        'is_active' => false,
    ]);

    $response = $this->actingAs($user)->patch(route('inventory.parameters.adjustment-reasons.toggle', $reason));

    $this->assertDatabaseHas('stock_adjustment_reasons', [
        'id' => $reason->id,
        'is_active' => true,
    ]);
});

test('user can delete an unused adjustment reason', function () {
    $user = User::factory()->create();
    $reason = StockAdjustmentReason::factory()->create(['name' => 'Motivo a Eliminar']);

    $response = $this->actingAs($user)->delete(route('inventory.parameters.adjustment-reasons.destroy', $reason));

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('stock_adjustment_reasons', ['id' => $reason->id]);
});
