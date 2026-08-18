<?php

use App\Models\Catalog\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('user can view units of measure management', function () {
    $user = User::factory()->create();
    UnitOfMeasure::factory()->count(2)->create();

    $this->actingAs($user)->get(route('catalog.units-of-measure.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('catalog/units-of-measure/index')->has('unitsOfMeasure', 2));
});

test('user can create and update a unit of measure', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('catalog.units-of-measure.store'), [
        'name' => '  Kilogramo ',
        'abbreviation' => ' kg ',
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $unit = UnitOfMeasure::firstOrFail();
    expect($unit)->name->toBe('Kilogramo')->abbreviation->toBe('kg');

    $this->actingAs($user)->put(route('catalog.units-of-measure.update', $unit), [
        'name' => 'Kilo',
        'abbreviation' => 'k',
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect($unit->fresh())->name->toBe('Kilo')->abbreviation->toBe('k');
});

test('unit name and abbreviation are unique ignoring case and outer spaces', function () {
    $user = User::factory()->create();
    UnitOfMeasure::factory()->create(['name' => 'Kilogramo', 'abbreviation' => 'kg']);

    $this->actingAs($user)->post(route('catalog.units-of-measure.store'), [
        'name' => ' KILOGRAMO ',
        'abbreviation' => 'otra',
    ])->assertSessionHasErrors(['name']);

    $this->actingAs($user)->post(route('catalog.units-of-measure.store'), [
        'name' => 'Gramo',
        'abbreviation' => ' KG ',
    ])->assertSessionHasErrors(['abbreviation']);
});

test('unit status can be toggled while articles are not implemented', function () {
    $user = User::factory()->create();
    $unit = UnitOfMeasure::factory()->create();

    $this->actingAs($user)->patch(route('catalog.units-of-measure.toggle', $unit))
        ->assertSessionHasNoErrors();

    expect($unit->fresh()->is_active)->toBeFalse();
});
