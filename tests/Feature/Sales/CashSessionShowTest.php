<?php

use App\Enums\Sales\CashMovementType;
use App\Enums\Sales\CashSessionStatus;
use App\Models\Sales\CashMovement;
use App\Models\Sales\CashSession;
use App\Models\Sales\PaymentMethod;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->cash = PaymentMethod::factory()->cash()->create(['name' => 'Efectivo']);
});

test('an open cash session shows its movements but not its totals nor the expected cash', function () {
    $user = User::factory()->create();
    $session = CashSession::factory()->create([
        'user_id' => $user->id,
        'opening_amount' => '8000.00',
    ]);
    CashMovement::factory()->opening()->create([
        'cash_session_id' => $session->id,
        'payment_method_id' => $this->cash->id,
        'amount' => '8000.00',
        'user_id' => $user->id,
    ]);
    CashMovement::factory()->create([
        'cash_session_id' => $session->id,
        'type' => CashMovementType::Income,
        'payment_method_id' => $this->cash->id,
        'amount' => '2000.00',
        'reason' => 'Refuerzo de cambio',
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('sales.cash-sessions.show', $session));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('sales/cash-sessions/show')
        ->has('cashSession', fn (Assert $sessionProp) => $sessionProp
            ->where('id', $session->id)
            ->where('status', 'abierta')
            ->where('is_open', true)
            ->where('totals', null)
            ->where('expected_totals', [])
            ->has('movements', 2)
            ->etc()
        )
    );
});

test('a closed cash session shows its totals and the expected cash', function () {
    $user = User::factory()->create();
    $session = CashSession::factory()->create([
        'user_id' => $user->id,
        'opening_amount' => '8000.00',
    ]);
    CashMovement::factory()->opening()->create([
        'cash_session_id' => $session->id,
        'payment_method_id' => $this->cash->id,
        'amount' => '8000.00',
        'user_id' => $user->id,
    ]);
    CashMovement::factory()->create([
        'cash_session_id' => $session->id,
        'type' => CashMovementType::Income,
        'payment_method_id' => $this->cash->id,
        'amount' => '2000.00',
        'reason' => 'Refuerzo de cambio',
        'user_id' => $user->id,
    ]);
    $session->update(['status' => CashSessionStatus::Closed, 'closed_at' => now()]);

    $this->actingAs($user)
        ->get(route('sales.cash-sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('cashSession.is_open', false)
            ->where('cashSession.totals.opening_amount', '8000.00')
            ->where('cashSession.totals.income_amount', '2000.00')
            ->where('cashSession.totals.expense_amount', '0.00')
            ->where('cashSession.totals.expected_cash', '10000.00')
            ->where('cashSession.expected_totals.0.expected_amount', '10000.00')
        );
});

test('current cash session route redirects to the open shift if cashier has one', function () {
    $user = User::factory()->create();
    $session = CashSession::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('sales.cash-sessions.current'));

    $response->assertRedirect(route('sales.cash-sessions.show', $session));
});

test('current cash session route redirects to create if cashier has no open shift', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('sales.cash-sessions.current'));

    $response->assertRedirect(route('sales.cash-sessions.create'));
});

test('registering a movement via HTTP creates the movement and redirects back with toast', function () {
    $user = User::factory()->create();
    $session = CashSession::factory()->create([
        'user_id' => $user->id,
        'opening_amount' => '10000.00',
    ]);
    CashMovement::factory()->opening()->create([
        'cash_session_id' => $session->id,
        'payment_method_id' => $this->cash->id,
        'amount' => '10000.00',
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('sales.cash-sessions.movements.store', $session), [
        'type' => 'egreso',
        'amount' => '3000.00',
        'reason' => 'Compra de artículos de limpieza',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    expect(CashMovement::count())->toBe(2);

    $expense = CashMovement::where('type', CashMovementType::Expense)->sole();

    expect($expense->amount)->toBe('3000.00')
        ->and($expense->reason)->toBe('Compra de artículos de limpieza')
        ->and($expense->cash_session_id)->toBe($session->id)
        ->and($expense->user_id)->toBe($user->id);

    expect($session->fresh()->expectedCash())->toBe('7000.00');
});

test('HTTP movement validation rejects empty reason or invalid amount', function () {
    $user = User::factory()->create();
    $session = CashSession::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('sales.cash-sessions.movements.store', $session), [
        'type' => 'ingreso',
        'amount' => '0',
        'reason' => '',
    ]);

    $response->assertSessionHasErrors(['amount', 'reason']);
});

test('HTTP movement validation rejects expense exceeding available cash', function () {
    $user = User::factory()->create();
    $session = CashSession::factory()->create([
        'user_id' => $user->id,
        'opening_amount' => '2000.00',
    ]);
    CashMovement::factory()->opening()->create([
        'cash_session_id' => $session->id,
        'payment_method_id' => $this->cash->id,
        'amount' => '2000.00',
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('sales.cash-sessions.movements.store', $session), [
        'type' => 'egreso',
        'amount' => '5000.00',
        'reason' => 'Gasto mayor al disponible',
    ]);

    /* The message leaves the available cash out, so the closing count stays blind. */
    $response->assertSessionHasErrors([
        'amount' => 'El importe del egreso supera el efectivo disponible en la caja según el sistema.',
    ]);
});
