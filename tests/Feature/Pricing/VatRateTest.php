<?php

use App\Models\Catalog\Article;
use App\Models\Pricing\VatRate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

test('guest cannot access vat rate management', function () {
    $this->get(route('pricing.vat-rates.index'))->assertRedirect(route('login'));
});

test('user can view vat rate management', function () {
    $user = User::factory()->create();
    VatRate::factory()->count(2)->create();

    $this->actingAs($user)->get(route('pricing.vat-rates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('pricing/vat-rates/index')->has('vatRates', 2));
});

test('user can create and update a vat rate', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pricing.vat-rates.store'), [
        'description' => '  General ',
        'percentage' => 21,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $vatRate = VatRate::firstOrFail();
    expect($vatRate->description)->toBe('General');
    expect($vatRate->percentage)->toBe(21.0);

    $this->actingAs($user)->put(route('pricing.vat-rates.update', $vatRate), [
        'description' => 'General (actualizada)',
        'percentage' => 22.5,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect($vatRate->fresh())
        ->description->toBe('General (actualizada)')
        ->percentage->toBe(22.5);
});

test('vat rate description is unique ignoring case and outer spaces', function () {
    $user = User::factory()->create();
    VatRate::factory()->create(['description' => 'Reducida']);

    $this->actingAs($user)->post(route('pricing.vat-rates.store'), [
        'description' => '  REDUCIDA ',
        'percentage' => 10.5,
    ])->assertSessionHasErrors(['description']);
});

test('database index protects normalized vat rate description uniqueness', function () {
    VatRate::factory()->create(['description' => 'Reducida']);

    expect(fn () => VatRate::factory()->create(['description' => ' REDUCIDA ']))
        ->toThrow(QueryException::class);
});

test('vat rate percentage must be between 0 and 100', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pricing.vat-rates.store'), [
        'description' => 'Fuera de rango negativo',
        'percentage' => -1,
    ])->assertSessionHasErrors(['percentage']);

    $this->actingAs($user)->post(route('pricing.vat-rates.store'), [
        'description' => 'Fuera de rango positivo',
        'percentage' => 101,
    ])->assertSessionHasErrors(['percentage']);
});

test('vat rate status can be toggled when it has no associated articles', function () {
    $user = User::factory()->create();
    $vatRate = VatRate::factory()->create();

    $this->actingAs($user)->patch(route('pricing.vat-rates.toggle', $vatRate))
        ->assertSessionHasNoErrors();

    expect($vatRate->fresh()->is_active)->toBeFalse();
});

test('vat rate cannot be deactivated while it has associated articles', function () {
    $user = User::factory()->create();
    $vatRate = VatRate::factory()->create();
    Article::factory()->create(['vat_rate_id' => $vatRate->id]);

    $this->actingAs($user)->patch(route('pricing.vat-rates.toggle', $vatRate))
        ->assertSessionHasErrors(['vat_rate']);

    expect($vatRate->fresh()->is_active)->toBeTrue();
});
