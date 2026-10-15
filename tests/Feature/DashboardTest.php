<?php

use App\Models\Organization\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard defaults to the current month up to today with every branch and channel', function () {
    $this->travelTo('2026-10-14 10:00:00');

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('filters.date_from', '2026-10-01')
            ->where('filters.date_to', '2026-10-14')
            ->where('filters.branch_id', null)
            ->where('filters.channel', null)
            ->where('revenues', null)
            ->where('expenses', null)
            ->where('evolution', null)
            ->where('operational', null)
        );
});

test('dashboard echoes back the applied filters', function () {
    $branch = Branch::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'branch_id' => $branch->id,
            'channel' => 'online',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.date_from', '2026-09-01')
            ->where('filters.date_to', '2026-09-30')
            ->where('filters.branch_id', $branch->id)
            ->where('filters.channel', 'online')
        );
});

test('dashboard lists only active branches as filter options', function () {
    $active = Branch::factory()->create(['name' => 'Centro', 'is_active' => true]);
    Branch::factory()->create(['name' => 'Cerrada', 'is_active' => false]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('branches', 1)
            ->where('branches.0.id', $active->id)
            ->where('branches.0.name', 'Centro')
        );
});

test('dashboard rejects invalid filters', function (array $query, string $field) {
    $this->travelTo('2026-10-14 10:00:00');

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard', $query))
        ->assertSessionHasErrors($field);
})->with([
    'date_to before date_from' => [['date_from' => '2026-10-10', 'date_to' => '2026-10-01'], 'date_to'],
    'end before default start' => [['date_to' => '2026-09-30'], 'date_to'],
    'start after default end' => [['date_from' => '2026-10-20'], 'date_to'],
    'empty start with end before default start' => [['date_from' => '', 'date_to' => '2026-09-30'], 'date_to'],
    'empty end with start after default end' => [['date_from' => '2026-10-20', 'date_to' => ''], 'date_to'],
    'malformed start' => [['date_from' => 'invalid'], 'date_from'],
    'malformed end' => [['date_to' => 'invalid'], 'date_to'],
    'zero start is not a default' => [['date_from' => '0'], 'date_from'],
    'zero end is not a default' => [['date_to' => '0'], 'date_to'],
    'unknown channel' => [['channel' => 'mayorista'], 'channel'],
    'missing branch' => [['branch_id' => 999999], 'branch_id'],
]);

test('dashboard validates and returns the effective date range', function (array $query, string $dateFrom, string $dateTo) {
    $this->travelTo('2026-10-14 10:00:00');

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard', $query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.date_from', $dateFrom)
            ->where('filters.date_to', $dateTo)
        );
})->with([
    'only end' => [['date_to' => '2026-10-10'], '2026-10-01', '2026-10-10'],
    'only start' => [['date_from' => '2026-10-10'], '2026-10-10', '2026-10-14'],
    'empty dates' => [['date_from' => '', 'date_to' => ''], '2026-10-01', '2026-10-14'],
    'null dates' => [['date_from' => null, 'date_to' => null], '2026-10-01', '2026-10-14'],
    'end equals default start' => [['date_to' => '2026-10-01'], '2026-10-01', '2026-10-01'],
    'start equals default end' => [['date_from' => '2026-10-14'], '2026-10-14', '2026-10-14'],
    'same explicit dates' => [['date_from' => '2026-10-10', 'date_to' => '2026-10-10'], '2026-10-10', '2026-10-10'],
    'valid future range' => [['date_from' => '2026-10-20', 'date_to' => '2026-10-21'], '2026-10-20', '2026-10-21'],
]);

test('dashboard shares validation errors after redirecting to the last valid filters and clears them on success', function () {
    $branch = Branch::factory()->create();
    $validFilters = [
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
        'branch_id' => $branch->id,
        'channel' => 'online',
    ];
    $validUrl = route('dashboard', $validFilters);

    $response = $this->actingAs(User::factory()->create())
        ->get($validUrl)
        ->assertOk();

    $this->from($validUrl)
        ->withCookie(config('session.cookie'), session()->getId())
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', $response->viewData('page')['version'])
        ->get(route('dashboard', [...$validFilters, 'date_from' => '2026-10-20']))
        ->assertRedirect($validUrl)
        ->assertSessionHasErrors('date_to');

    $this->get($validUrl)
        ->assertOk()
        ->assertJsonPath('props.filters', $validFilters)
        ->assertJsonStructure(['props' => ['errors' => ['date_to']]]);

    $this->get(route('dashboard', [...$validFilters, 'date_to' => '2026-10-20']))
        ->assertOk()
        ->assertJsonPath('props.filters.date_to', '2026-10-20')
        ->assertJsonPath('props.errors', []);
});
