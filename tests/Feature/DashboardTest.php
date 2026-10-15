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
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard', $query))
        ->assertSessionHasErrors($field);
})->with([
    'date_to before date_from' => [['date_from' => '2026-10-10', 'date_to' => '2026-10-01'], 'date_to'],
    'unknown channel' => [['channel' => 'mayorista'], 'channel'],
    'missing branch' => [['branch_id' => 999999], 'branch_id'],
]);
