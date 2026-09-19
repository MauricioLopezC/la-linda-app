<?php

use App\Enums\Pricing\PriceListChannel;
use App\Enums\Pricing\PriceListValidityStatus;
use App\Models\Pricing\PriceList;
use App\Models\User;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

test('guest cannot access price list management', function () {
    $this->get(route('pricing.price-lists.index'))->assertRedirect(route('login'));
});

test('user can view price list management', function () {
    $user = User::factory()->create();
    PriceList::factory()->count(2)->create(['channel' => 'mostrador']);

    $this->actingAs($user)->get(route('pricing.price-lists.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('pricing/price-lists/index')->has('priceLists', 2));
});

test('user can create and update a price list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => '  Lista Mostrador ',
        'channel' => 'mostrador',
        'valid_from' => now()->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $priceList = PriceList::firstOrFail();
    expect($priceList->name)->toBe('Lista Mostrador');
    expect($priceList->channel)->toBe(PriceListChannel::Mostrador);

    $this->actingAs($user)->put(route('pricing.price-lists.update', $priceList), [
        'name' => 'Lista Mostrador (actualizada)',
        'channel' => 'mostrador',
        'valid_from' => $priceList->valid_from->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect($priceList->fresh()->name)->toBe('Lista Mostrador (actualizada)');
});

test('price list name is unique ignoring case and outer spaces', function () {
    $user = User::factory()->create();
    PriceList::factory()->create(['name' => 'Lista Online', 'channel' => 'online']);

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => '  LISTA ONLINE ',
        'channel' => 'mostrador',
        'valid_from' => now()->toDateString(),
    ])->assertSessionHasErrors(['name']);
});

test('database index protects normalized price list name uniqueness', function () {
    PriceList::factory()->create(['name' => 'Lista Online', 'channel' => 'online']);

    expect(fn () => PriceList::factory()->create(['name' => ' LISTA ONLINE ', 'channel' => 'mostrador']))
        ->toThrow(QueryException::class);
});

test('valid_to cannot be earlier than valid_from', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => 'Lista Inválida',
        'channel' => 'mostrador',
        'valid_from' => now()->toDateString(),
        'valid_to' => now()->subDay()->toDateString(),
    ])->assertSessionHasErrors(['valid_to']);
});

test('cannot create two active overlapping price lists for the same channel', function () {
    $user = User::factory()->create();
    PriceList::factory()->create([
        'channel' => 'mostrador',
        'valid_from' => now()->subDays(5)->toDateString(),
        'valid_to' => now()->addDays(5)->toDateString(),
        'is_active' => true,
    ]);

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => 'Lista Solapada',
        'channel' => 'mostrador',
        'valid_from' => now()->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ])->assertSessionHasErrors(['valid_from']);
});

test('non overlapping periods and different channels are allowed', function () {
    $user = User::factory()->create();
    PriceList::factory()->create([
        'channel' => 'mostrador',
        'valid_from' => now()->subDays(10)->toDateString(),
        'valid_to' => now()->subDays(5)->toDateString(),
        'is_active' => true,
    ]);

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => 'Lista Mostrador Nueva',
        'channel' => 'mostrador',
        'valid_from' => now()->subDays(4)->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => 'Lista Online Nueva',
        'channel' => 'online',
        'valid_from' => now()->subDays(10)->toDateString(),
        'valid_to' => now()->addDays(10)->toDateString(),
        'is_active' => true,
    ])->assertSessionHasNoErrors();
});

test('inactive overlapping price lists do not block creation', function () {
    $user = User::factory()->create();
    PriceList::factory()->create([
        'channel' => 'mostrador',
        'valid_from' => now()->subDays(5)->toDateString(),
        'valid_to' => now()->addDays(5)->toDateString(),
        'is_active' => false,
    ]);

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => 'Lista Mostrador Activa',
        'channel' => 'mostrador',
        'valid_from' => now()->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ])->assertSessionHasNoErrors();
});

test('the last active vigente general price list cannot be deactivated', function () {
    $user = User::factory()->create();
    $generalList = PriceList::factory()->create([
        'channel' => 'general',
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ]);

    $this->actingAs($user)->patch(route('pricing.price-lists.toggle', $generalList))
        ->assertSessionHasErrors(['price_list']);

    expect($generalList->fresh()->is_active)->toBeTrue();
});

test('a general price list can be deactivated when another vigente general list exists', function () {
    $user = User::factory()->create();
    PriceList::factory()->create([
        'channel' => 'general',
        'valid_from' => now()->subMonths(2)->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ]);
    $secondGeneralList = PriceList::factory()->create([
        'channel' => 'online',
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ]);

    // Reassign to a second, independent general list so deactivating it still leaves one vigente.
    $secondGeneralList->update(['channel' => PriceListChannel::General]);

    $this->actingAs($user)->patch(route('pricing.price-lists.toggle', $secondGeneralList))
        ->assertSessionHasNoErrors();

    expect($secondGeneralList->fresh()->is_active)->toBeFalse();
});

test('price list status can be toggled for non general channels', function () {
    $user = User::factory()->create();
    $priceList = PriceList::factory()->create(['channel' => 'mostrador']);

    $this->actingAs($user)->patch(route('pricing.price-lists.toggle', $priceList))
        ->assertSessionHasNoErrors();

    expect($priceList->fresh()->is_active)->toBeFalse();
});

test('computed validity status reflects date range', function () {
    $vigente = PriceList::factory()->create([
        'channel' => 'mostrador',
        'valid_from' => now()->subDay()->toDateString(),
        'valid_to' => null,
    ]);
    $futura = PriceList::factory()->create([
        'channel' => 'online',
        'valid_from' => now()->addWeek()->toDateString(),
        'valid_to' => null,
    ]);
    $vencida = PriceList::factory()->create([
        'channel' => 'general',
        'valid_from' => now()->subMonths(2)->toDateString(),
        'valid_to' => now()->subMonth()->toDateString(),
    ]);

    expect($vigente->validityStatus())->toBe(PriceListValidityStatus::Vigente);
    expect($futura->validityStatus())->toBe(PriceListValidityStatus::Futura);
    expect($vencida->validityStatus())->toBe(PriceListValidityStatus::Vencida);
});
