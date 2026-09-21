<?php

use App\Enums\Pricing\PriceListChannel;
use App\Enums\Pricing\PriceListScope;
use App\Enums\Pricing\PriceListValidityStatus;
use App\Models\Pricing\PriceList;
use App\Models\User;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Every test that touches a general list needs the base list the system must never be without,
 * since the seeder is not run inside the test suite.
 */
function generalPriceList(?string $validTo = null): PriceList
{
    return PriceList::factory()->forChannel(PriceListChannel::General)->create([
        'name' => 'Lista General',
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_to' => $validTo,
    ]);
}

test('guest cannot access price list management', function () {
    $this->get(route('pricing.price-lists.index'))->assertRedirect(route('login'));
});

test('user can view price list management', function () {
    $user = User::factory()->create();
    PriceList::factory()->count(2)->forChannel('mostrador')->create();

    $this->actingAs($user)->get(route('pricing.price-lists.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('pricing/price-lists/index')->has('priceLists', 2));
});

test('user can create and update a price list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => '  Lista Mostrador ',
        'scope' => 'canal',
        'channel' => 'mostrador',
        'valid_from' => now()->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $priceList = PriceList::firstOrFail();
    expect($priceList->name)->toBe('Lista Mostrador');
    expect($priceList->scope)->toBe(PriceListScope::Canal);
    expect($priceList->channel)->toBe(PriceListChannel::Mostrador);

    $this->actingAs($user)->put(route('pricing.price-lists.update', $priceList), [
        'name' => 'Lista Mostrador (actualizada)',
        'scope' => 'canal',
        'channel' => 'mostrador',
        'valid_from' => $priceList->valid_from->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect($priceList->fresh()->name)->toBe('Lista Mostrador (actualizada)');
});

test('price list name is unique ignoring case and outer spaces', function () {
    $user = User::factory()->create();
    PriceList::factory()->forChannel('online')->create(['name' => 'Lista Online']);

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => '  LISTA ONLINE ',
        'scope' => 'canal',
        'channel' => 'mostrador',
        'valid_from' => now()->toDateString(),
    ])->assertSessionHasErrors(['name']);
});

test('database index protects normalized price list name uniqueness', function () {
    PriceList::factory()->forChannel('online')->create(['name' => 'Lista Online']);

    expect(fn () => PriceList::factory()->forChannel('mostrador')->create(['name' => ' LISTA ONLINE ']))
        ->toThrow(QueryException::class);
});

test('valid_to cannot be earlier than valid_from', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => 'Lista Inválida',
        'scope' => 'canal',
        'channel' => 'mostrador',
        'valid_from' => now()->toDateString(),
        'valid_to' => now()->subDay()->toDateString(),
    ])->assertSessionHasErrors(['valid_to']);
});

test('a channel list must declare its channel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => 'Lista Sin Canal',
        'scope' => 'canal',
        'channel' => null,
        'valid_from' => now()->toDateString(),
    ])->assertSessionHasErrors(['channel']);
});

test('a preferential list is stored without a channel even if one is submitted', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => 'Lista Mayorista',
        'scope' => 'particular',
        'channel' => 'mostrador',
        'valid_from' => now()->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $priceList = PriceList::where('name', 'Lista Mayorista')->firstOrFail();
    expect($priceList->scope)->toBe(PriceListScope::Particular);
    expect($priceList->channel)->toBeNull();
});

test('cannot create two active overlapping channel lists for the same channel', function () {
    $user = User::factory()->create();
    PriceList::factory()->forChannel('mostrador')->create([
        'valid_from' => now()->subDays(5)->toDateString(),
        'valid_to' => now()->addDays(5)->toDateString(),
    ]);

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => 'Lista Solapada',
        'scope' => 'canal',
        'channel' => 'mostrador',
        'valid_from' => now()->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ])->assertSessionHasErrors(['valid_from']);
});

test('preferential lists may overlap channel lists and each other', function () {
    $user = User::factory()->create();
    generalPriceList();
    PriceList::factory()->particular()->create([
        'name' => 'Lista Mayorista',
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_to' => null,
    ]);

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => 'Lista Distribuidores',
        'scope' => 'particular',
        'channel' => null,
        'valid_from' => now()->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect(PriceList::query()->where('scope', PriceListScope::Particular)->count())->toBe(2);
});

test('the sprint review demo scenario is reproducible', function () {
    $user = User::factory()->create();
    generalPriceList();

    foreach ([
        ['name' => 'Lista Mostrador', 'scope' => 'canal', 'channel' => 'mostrador'],
        ['name' => 'Lista Mayorista', 'scope' => 'particular', 'channel' => null],
    ] as $payload) {
        $this->actingAs($user)->post(route('pricing.price-lists.store'), [
            ...$payload,
            'valid_from' => now()->toDateString(),
            'valid_to' => null,
            'is_active' => true,
        ])->assertSessionHasNoErrors();
    }

    expect(PriceList::query()->active()->currentlyValid()->count())->toBe(3);
});

test('non overlapping periods and different channels are allowed', function () {
    $user = User::factory()->create();
    PriceList::factory()->forChannel('mostrador')->create([
        'valid_from' => now()->subDays(10)->toDateString(),
        'valid_to' => now()->subDays(5)->toDateString(),
    ]);

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => 'Lista Mostrador Nueva',
        'scope' => 'canal',
        'channel' => 'mostrador',
        'valid_from' => now()->subDays(4)->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => 'Lista Online Nueva',
        'scope' => 'canal',
        'channel' => 'online',
        'valid_from' => now()->subDays(10)->toDateString(),
        'valid_to' => now()->addDays(10)->toDateString(),
        'is_active' => true,
    ])->assertSessionHasNoErrors();
});

test('a channel list can be succeeded by closing the current one first', function () {
    $user = User::factory()->create();
    $current = PriceList::factory()->forChannel('mostrador')->create([
        'name' => 'Mostrador Actual',
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_to' => null,
    ]);

    $this->actingAs($user)->put(route('pricing.price-lists.update', $current), [
        'name' => 'Mostrador Actual',
        'scope' => 'canal',
        'channel' => 'mostrador',
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_to' => now()->endOfMonth()->toDateString(),
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => 'Mostrador Próxima Temporada',
        'scope' => 'canal',
        'channel' => 'mostrador',
        'valid_from' => now()->addMonth()->startOfMonth()->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ])->assertSessionHasNoErrors();
});

test('inactive overlapping price lists do not block creation', function () {
    $user = User::factory()->create();
    PriceList::factory()->forChannel('mostrador')->inactive()->create([
        'valid_from' => now()->subDays(5)->toDateString(),
        'valid_to' => now()->addDays(5)->toDateString(),
    ]);

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => 'Lista Mostrador Activa',
        'scope' => 'canal',
        'channel' => 'mostrador',
        'valid_from' => now()->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ])->assertSessionHasNoErrors();
});

test('an inactive price list cannot be reactivated when it overlaps an active one', function () {
    $user = User::factory()->create();
    PriceList::factory()->forChannel('mostrador')->create([
        'valid_from' => now()->subDays(5)->toDateString(),
        'valid_to' => null,
    ]);
    $inactiveList = PriceList::factory()->forChannel('mostrador')->inactive()->create([
        'valid_from' => now()->toDateString(),
        'valid_to' => now()->addDays(10)->toDateString(),
    ]);

    $this->actingAs($user)->patch(route('pricing.price-lists.toggle', $inactiveList))
        ->assertSessionHasErrors(['price_list']);

    expect($inactiveList->fresh()->is_active)->toBeFalse();
});

test('an inactive price list can be reactivated when its period is free', function () {
    $user = User::factory()->create();
    PriceList::factory()->forChannel('mostrador')->create([
        'valid_from' => now()->subDays(20)->toDateString(),
        'valid_to' => now()->subDays(10)->toDateString(),
    ]);
    $inactiveList = PriceList::factory()->forChannel('mostrador')->inactive()->create([
        'valid_from' => now()->subDays(5)->toDateString(),
        'valid_to' => null,
    ]);

    $this->actingAs($user)->patch(route('pricing.price-lists.toggle', $inactiveList))
        ->assertSessionHasNoErrors();

    expect($inactiveList->fresh()->is_active)->toBeTrue();
});

test('a preferential list can always be reactivated', function () {
    $user = User::factory()->create();
    PriceList::factory()->particular()->create(['valid_from' => now()->subMonth()->toDateString()]);
    $inactiveList = PriceList::factory()->particular()->inactive()->create([
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_to' => null,
    ]);

    $this->actingAs($user)->patch(route('pricing.price-lists.toggle', $inactiveList))
        ->assertSessionHasNoErrors();

    expect($inactiveList->fresh()->is_active)->toBeTrue();
});

test('the last active general price list cannot be deactivated', function () {
    $user = User::factory()->create();
    $generalList = generalPriceList();

    $this->actingAs($user)->patch(route('pricing.price-lists.toggle', $generalList))
        ->assertSessionHasErrors(['price_list']);

    expect($generalList->fresh()->is_active)->toBeTrue();
});

test('the only general price list cannot be given an end date without a successor', function () {
    $user = User::factory()->create();
    $generalList = generalPriceList();

    $this->actingAs($user)->put(route('pricing.price-lists.update', $generalList), [
        'name' => 'Lista General',
        'scope' => 'canal',
        'channel' => 'general',
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_to' => now()->addDay()->toDateString(),
        'is_active' => true,
    ])->assertSessionHasErrors(['price_list']);

    expect($generalList->fresh()->valid_to)->toBeNull();
});

test('the general price list can be handed over to an open ended successor', function () {
    $user = User::factory()->create();
    $current = generalPriceList(now()->endOfMonth()->toDateString());
    PriceList::factory()->forChannel(PriceListChannel::General)->create([
        'name' => 'Lista General Próxima',
        'valid_from' => now()->addMonth()->startOfMonth()->toDateString(),
        'valid_to' => null,
    ]);

    $this->actingAs($user)->put(route('pricing.price-lists.update', $current), [
        'name' => 'Lista General',
        'scope' => 'canal',
        'channel' => 'general',
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_to' => now()->endOfMonth()->toDateString(),
        'is_active' => true,
    ])->assertSessionHasNoErrors();
});

test('the general channel cannot be left uncovered through a gap between lists', function () {
    generalPriceList(now()->endOfMonth()->toDateString());
    PriceList::factory()->forChannel(PriceListChannel::General)->create([
        'name' => 'Lista General Tardía',
        'valid_from' => now()->addMonth()->startOfMonth()->addDays(3)->toDateString(),
        'valid_to' => null,
    ]);

    expect(PriceList::generalCoverageIsContinuous())->toBeFalse();
});

test('a general price list can be deactivated when another covers the period', function () {
    $user = User::factory()->create();
    generalPriceList();
    $redundant = PriceList::factory()->forChannel(PriceListChannel::General)->create([
        'name' => 'Lista General Duplicada',
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_to' => null,
    ]);

    $this->actingAs($user)->patch(route('pricing.price-lists.toggle', $redundant))
        ->assertSessionHasNoErrors();

    expect($redundant->fresh()->is_active)->toBeFalse();
});

test('the general list cannot be turned into a preferential one', function () {
    $user = User::factory()->create();
    $generalList = generalPriceList();

    $this->actingAs($user)->put(route('pricing.price-lists.update', $generalList), [
        'name' => 'Lista General',
        'scope' => 'particular',
        'channel' => null,
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_to' => null,
        'is_active' => true,
    ])->assertSessionHasErrors(['price_list']);

    expect($generalList->fresh()->channel)->toBe(PriceListChannel::General);
});

test('price list status can be toggled for non general channels', function () {
    $user = User::factory()->create();
    $priceList = PriceList::factory()->forChannel('mostrador')->create();

    $this->actingAs($user)->patch(route('pricing.price-lists.toggle', $priceList))
        ->assertSessionHasNoErrors();

    expect($priceList->fresh()->is_active)->toBeFalse();
});

test('computed validity status reflects date range', function () {
    $vigente = PriceList::factory()->forChannel('mostrador')->create([
        'valid_from' => now()->subDay()->toDateString(),
        'valid_to' => null,
    ]);
    $futura = PriceList::factory()->forChannel('online')->futura()->create();
    $vencida = PriceList::factory()->particular()->vencida()->create();

    expect($vigente->validityStatus())->toBe(PriceListValidityStatus::Vigente);
    expect($futura->validityStatus())->toBe(PriceListValidityStatus::Futura);
    expect($vencida->validityStatus())->toBe(PriceListValidityStatus::Vencida);
});

test('a price list that starts today is already in effect', function () {
    PriceList::factory()->forChannel('mostrador')->create([
        'valid_from' => now()->toDateString(),
        'valid_to' => now()->toDateString(),
    ]);

    expect(PriceList::query()->currentlyValid()->count())->toBe(1);
});

test('a period that starts the same day another one ends is detected as overlapping', function () {
    $user = User::factory()->create();
    PriceList::factory()->forChannel('mostrador')->create([
        'valid_from' => now()->subMonth()->toDateString(),
        'valid_to' => now()->addDays(10)->toDateString(),
    ]);

    $this->actingAs($user)->post(route('pricing.price-lists.store'), [
        'name' => 'Lista Borde',
        'scope' => 'canal',
        'channel' => 'mostrador',
        'valid_from' => now()->addDays(5)->toDateString(),
        'valid_to' => now()->addDays(10)->toDateString(),
        'is_active' => true,
    ])->assertSessionHasErrors(['valid_from']);
});
