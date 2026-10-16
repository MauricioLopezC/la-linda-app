<?php

use App\Actions\Inventory\ToggleWarehouseStatus;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->branch = Branch::factory()->create(['is_active' => true]);
    $this->user = User::factory()->administrador()->create();
    $this->toggleAction = app(ToggleWarehouseStatus::class);
});

test('database prevents having two active warehouses marked as online channel', function () {
    Warehouse::factory()->online()->create([
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);

    expect(inSavepoint(fn () => Warehouse::factory()->online()->create([
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ])))->toThrow(QueryException::class);
});

test('database allows multiple inactive warehouses marked as online channel', function () {
    $wh1 = Warehouse::factory()->online()->create([
        'branch_id' => $this->branch->id,
        'is_active' => false,
    ]);

    $wh2 = Warehouse::factory()->online()->create([
        'branch_id' => $this->branch->id,
        'is_active' => false,
    ]);

    $wh3 = Warehouse::factory()->online()->create([
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);

    expect(Warehouse::query()->online()->count())->toBe(3)
        ->and(Warehouse::query()->online()->active()->count())->toBe(1);
});

test('toggle warehouse status rejects activating an online warehouse when another active online warehouse exists', function () {
    $activeOnline = Warehouse::factory()->online()->create([
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);

    $inactiveOnline = Warehouse::factory()->online()->create([
        'branch_id' => $this->branch->id,
        'is_active' => false,
    ]);

    expect(fn () => $this->toggleAction->handle($inactiveOnline))
        ->toThrow(ValidationException::class, 'Ya existe un depósito activo asignado al canal online.');

    // If active online is deactivated first, then inactiveOnline can be activated successfully
    $this->toggleAction->handle($activeOnline);
    expect($activeOnline->fresh()->is_active)->toBeFalse();

    $this->toggleAction->handle($inactiveOnline);
    expect($inactiveOnline->fresh()->is_active)->toBeTrue();
});

test('warehouse store endpoint rejects creating a second active online warehouse', function () {
    Warehouse::factory()->online()->create([
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->post(route('inventory.warehouses.store'), [
        'name' => 'Otro Depósito Web',
        'branch_id' => $this->branch->id,
        'is_online_channel' => true,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors(['is_online_channel']);
});
