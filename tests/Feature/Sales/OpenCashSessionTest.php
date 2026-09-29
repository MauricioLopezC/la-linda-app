<?php

use App\Actions\Sales\OpenCashSession;
use App\Enums\Sales\CashCountMoment;
use App\Enums\Sales\CashDenomination;
use App\Enums\Sales\CashMovementType;
use App\Models\Sales\CashCount;
use App\Models\Sales\CashMovement;
use App\Models\Sales\CashSession;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\PointOfSale;
use App\Models\User;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * An opening count with every denomination at zero, overridden by the given quantities.
 *
 * @param  array<int, int>  $quantities
 * @return array<int, int>
 */
function openingCounts(array $quantities = []): array
{
    $counts = [];

    foreach (CashDenomination::cases() as $denomination) {
        $counts[$denomination->value] = $quantities[$denomination->value] ?? 0;
    }

    return $counts;
}

beforeEach(function () {
    $this->cash = PaymentMethod::factory()->cash()->create(['name' => 'Efectivo']);
});

test('opening with 10 bills of $10.000 and 5 of $2.000 gives a $110.000 opening amount', function () {
    $user = User::factory()->create();
    $pointOfSale = PointOfSale::factory()->create();

    $response = $this->actingAs($user)->post(route('sales.cash-sessions.store'), [
        'point_of_sale_id' => $pointOfSale->id,
        'counts' => openingCounts([10000 => 10, 2000 => 5]),
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('sales.sales.index'));

    $session = CashSession::sole();

    expect($session->opening_amount)->toBe('110000.00')
        ->and($session->isOpen())->toBeTrue()
        ->and($session->user_id)->toBe($user->id)
        ->and($session->point_of_sale_id)->toBe($pointOfSale->id)
        ->and($session->opened_at)->not->toBeNull()
        ->and($session->openingCounts()->count())->toBe(count(CashDenomination::cases()))
        ->and($session->openingCounts()->where('denomination', 10000)->value('quantity'))->toBe(10)
        ->and($session->openingCounts()->where('denomination', 2000)->value('quantity'))->toBe(5);

    $movement = CashMovement::sole();

    expect($movement->type)->toBe(CashMovementType::Opening)
        ->and($movement->amount)->toBe('110000.00')
        ->and($movement->payment_method_id)->toBe($this->cash->id)
        ->and($movement->cash_session_id)->toBe($session->id)
        ->and($movement->user_id)->toBe($user->id);
});

test('a second opening of the same point of sale is rejected', function () {
    $pointOfSale = PointOfSale::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->actingAs($first)->post(route('sales.cash-sessions.store'), [
        'point_of_sale_id' => $pointOfSale->id,
        'counts' => openingCounts([1000 => 3]),
    ])->assertSessionHasNoErrors();

    $this->actingAs($second)->post(route('sales.cash-sessions.store'), [
        'point_of_sale_id' => $pointOfSale->id,
        'counts' => openingCounts([1000 => 3]),
    ])->assertSessionHasErrors('point_of_sale_id');

    expect(CashSession::count())->toBe(1)
        ->and(CashMovement::count())->toBe(1);
});

test('a cashier with an open session cannot open another point of sale', function () {
    $user = User::factory()->create();
    CashSession::factory()->create(['user_id' => $user->id]);
    $other = PointOfSale::factory()->create();

    $this->actingAs($user)->post(route('sales.cash-sessions.store'), [
        'point_of_sale_id' => $other->id,
        'counts' => openingCounts(),
    ])->assertSessionHasErrors('point_of_sale_id');

    expect(CashSession::count())->toBe(1);
});

test('a closed session does not block opening the point of sale again', function () {
    $user = User::factory()->create();
    $pointOfSale = PointOfSale::factory()->create();
    CashSession::factory()->closed()->create(['point_of_sale_id' => $pointOfSale->id, 'user_id' => $user->id]);

    $this->actingAs($user)->post(route('sales.cash-sessions.store'), [
        'point_of_sale_id' => $pointOfSale->id,
        'counts' => openingCounts([500 => 2]),
    ])->assertSessionHasNoErrors();

    expect(CashSession::query()->openForUser($user->id)->sole()->opening_amount)->toBe('1000.00');
});

test('an inactive point of sale cannot be opened', function () {
    $pointOfSale = PointOfSale::factory()->create(['is_active' => false]);

    $this->actingAs(User::factory()->create())->post(route('sales.cash-sessions.store'), [
        'point_of_sale_id' => $pointOfSale->id,
        'counts' => openingCounts([1000 => 1]),
    ])->assertSessionHasErrors('point_of_sale_id');

    expect(CashSession::count())->toBe(0);
});

test('a zero opening opens the session without an opening movement', function () {
    $pointOfSale = PointOfSale::factory()->create();

    $this->actingAs(User::factory()->create())->post(route('sales.cash-sessions.store'), [
        'point_of_sale_id' => $pointOfSale->id,
        'counts' => openingCounts(),
    ])->assertSessionHasNoErrors();

    expect(CashSession::sole()->opening_amount)->toBe('0.00')
        ->and(CashCount::where('moment', CashCountMoment::Opening)->count())->toBe(count(CashDenomination::cases()))
        ->and(CashMovement::count())->toBe(0);
});

test('opening is rejected when there is no active cash payment method', function () {
    $this->cash->update(['is_active' => false]);
    PaymentMethod::factory()->card()->create();

    $this->actingAs(User::factory()->create())->post(route('sales.cash-sessions.store'), [
        'point_of_sale_id' => PointOfSale::factory()->create()->id,
        'counts' => openingCounts([1000 => 1]),
    ])->assertSessionHasErrors('point_of_sale_id');

    expect(CashSession::count())->toBe(0)
        ->and(CashCount::count())->toBe(0);
});

test('the opening amount is always the sum of the count, never a typed value', function () {
    $this->actingAs(User::factory()->create())->post(route('sales.cash-sessions.store'), [
        'point_of_sale_id' => PointOfSale::factory()->create()->id,
        'counts' => openingCounts([20000 => 1, 10 => 3]),
        'opening_amount' => '999999.00',
    ])->assertSessionHasNoErrors();

    expect(CashSession::sole()->opening_amount)->toBe('20030.00');
});

test('the count only accepts whole, non-negative quantities of current bills', function (array $counts, string $errorKey) {
    $this->actingAs(User::factory()->create())->post(route('sales.cash-sessions.store'), [
        'point_of_sale_id' => PointOfSale::factory()->create()->id,
        'counts' => $counts,
    ])->assertSessionHasErrors($errorKey);

    expect(CashSession::count())->toBe(0);
})->with([
    'a coin denomination' => [openingCounts() + [5 => 1], 'counts'],
    'a missing denomination' => [array_diff_key(openingCounts(), [50 => 0]), 'counts'],
    'a negative quantity' => [openingCounts([1000 => -1]), 'counts.1000'],
    'a decimal quantity' => [openingCounts([1000 => 1.5]), 'counts.1000'],
]);

test('the schema still rejects a second open session at the same point of sale', function () {
    $session = CashSession::factory()->create();

    expect(inSavepoint(fn () => CashSession::factory()->create(['point_of_sale_id' => $session->point_of_sale_id])))
        ->toThrow(QueryException::class);
});

test('the action can be called directly for a given user', function () {
    $user = User::factory()->create();
    $pointOfSale = PointOfSale::factory()->create();

    $session = app(OpenCashSession::class)->handle([
        'point_of_sale_id' => $pointOfSale->id,
        'counts' => openingCounts([100 => 7]),
    ], $user->id);

    expect($session->user_id)->toBe($user->id)
        ->and($session->opening_amount)->toBe('700.00');
});

test('the open screen lists active points of sale and marks the taken ones', function () {
    $free = PointOfSale::factory()->create(['number' => 1]);
    $taken = PointOfSale::factory()->create(['number' => 2]);
    PointOfSale::factory()->create(['number' => 3, 'is_active' => false]);
    $holder = User::factory()->create(['name' => 'Ana Cajera']);
    CashSession::factory()->create(['point_of_sale_id' => $taken->id, 'user_id' => $holder->id]);

    $this->actingAs(User::factory()->create())
        ->get(route('sales.cash-sessions.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sales/cash-sessions/create')
            ->has('pointsOfSale', 2)
            ->where('pointsOfSale.0.id', $free->id)
            ->where('pointsOfSale.0.open_session_user_name', null)
            ->where('pointsOfSale.1.open_session_user_name', 'Ana Cajera')
            ->has('denominations', count(CashDenomination::cases()))
            ->where('denominations.0.label', '$20.000')
        );
});

test('the open screen redirects a cashier who already has an open session', function () {
    $user = User::factory()->create();
    CashSession::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('sales.cash-sessions.create'))
        ->assertRedirect(route('sales.sales.index'));
});

test('every page shares the cashier open session, or null without one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('cashSession', null));

    $session = CashSession::factory()->create(['user_id' => $user->id, 'opening_amount' => '1500.00']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cashSession.id', $session->id)
            ->where('cashSession.point_of_sale_number', $session->pointOfSale->number)
            ->where('cashSession.opening_amount', '1500.00')
        );
});
