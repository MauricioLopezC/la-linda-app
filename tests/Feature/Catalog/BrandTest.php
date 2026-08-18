<?php

use App\Models\Catalog\Brand;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('user can view brand management', function () {
    $user = User::factory()->create();
    Brand::factory()->count(2)->create();

    $this->actingAs($user)->get(route('catalog.brands.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('catalog/brands/index')->has('brands', 2));
});

test('user can create and update a brand', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('catalog.brands.store'), [
        'name' => '  Arcor ',
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $brand = Brand::firstOrFail();
    expect($brand->name)->toBe('Arcor');

    $this->actingAs($user)->put(route('catalog.brands.update', $brand), [
        'name' => 'Arcor Alimentos',
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect($brand->fresh()->name)->toBe('Arcor Alimentos');
});

test('brand name is unique ignoring case and outer spaces', function () {
    $user = User::factory()->create();
    Brand::factory()->create(['name' => 'La Serenísima']);

    $this->actingAs($user)->post(route('catalog.brands.store'), [
        'name' => '  LA SERENÍSIMA ',
    ])->assertSessionHasErrors(['name']);
});

test('brand status can be toggled while articles are not implemented', function () {
    $user = User::factory()->create();
    $brand = Brand::factory()->create();

    $this->actingAs($user)->patch(route('catalog.brands.toggle', $brand))
        ->assertSessionHasNoErrors();

    expect($brand->fresh()->is_active)->toBeFalse();
});
