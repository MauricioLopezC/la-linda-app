<?php

use App\Actions\Sales\RegisterCashMovement;
use App\Enums\Sales\CashMovementType;
use App\Models\Sales\CashMovement;
use App\Models\Sales\CashSession;
use App\Models\Sales\PaymentMethod;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->cash = PaymentMethod::factory()->cash()->create(['name' => 'Efectivo']);
    $this->action = app(RegisterCashMovement::class);
});

test('registering an income in cash increases the expected cash of the session', function () {
    $user = User::factory()->create();
    $session = CashSession::factory()->create([
        'user_id' => $user->id,
        'opening_amount' => '5000.00',
    ]);
    CashMovement::factory()->opening()->create([
        'cash_session_id' => $session->id,
        'payment_method_id' => $this->cash->id,
        'amount' => '5000.00',
        'user_id' => $user->id,
    ]);

    expect($session->expectedCash())->toBe('5000.00');

    $movement = $this->action->handle($session, [
        'type' => 'ingreso',
        'amount' => '2500.00',
        'reason' => 'Refuerzo de cambio de tesorería',
    ], $user->id);

    expect($movement->type)->toBe(CashMovementType::Income)
        ->and($movement->amount)->toBe('2500.00')
        ->and($movement->reason)->toBe('Refuerzo de cambio de tesorería')
        ->and($movement->payment_method_id)->toBe($this->cash->id)
        ->and($movement->user_id)->toBe($user->id)
        ->and($movement->cash_session_id)->toBe($session->id)
        ->and($session->expectedCash())->toBe('7500.00');
});

test('acceptance verification: registering an expense of $3.000 for cleaning supplies decreases expected cash by $3.000', function () {
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

    expect($session->expectedCash())->toBe('10000.00');

    $movement = $this->action->handle($session, [
        'type' => 'egreso',
        'amount' => '3000.00',
        'reason' => 'Compra de artículos de limpieza',
    ], $user->id);

    expect($movement->type)->toBe(CashMovementType::Expense)
        ->and($movement->amount)->toBe('3000.00')
        ->and($movement->reason)->toBe('Compra de artículos de limpieza')
        ->and($session->expectedCash())->toBe('7000.00');
});

test('an expense cannot exceed the available cash according to the system', function () {
    $user = User::factory()->create();
    $session = CashSession::factory()->create([
        'user_id' => $user->id,
        'opening_amount' => '5000.00',
    ]);
    CashMovement::factory()->opening()->create([
        'cash_session_id' => $session->id,
        'payment_method_id' => $this->cash->id,
        'amount' => '5000.00',
        'user_id' => $user->id,
    ]);

    expect(fn () => $this->action->handle($session, [
        'type' => 'egreso',
        'amount' => '5000.01',
        'reason' => 'Retiro que supera el saldo',
    ], $user->id))->toThrow(ValidationException::class);

    expect($session->expectedCash())->toBe('5000.00')
        ->and($session->movements()->where('type', CashMovementType::Expense)->count())->toBe(0);
});

test('an expense exactly equal to the available cash is accepted and leaves zero balance', function () {
    $user = User::factory()->create();
    $session = CashSession::factory()->create([
        'user_id' => $user->id,
        'opening_amount' => '4000.00',
    ]);
    CashMovement::factory()->opening()->create([
        'cash_session_id' => $session->id,
        'payment_method_id' => $this->cash->id,
        'amount' => '4000.00',
        'user_id' => $user->id,
    ]);

    $this->action->handle($session, [
        'type' => 'egreso',
        'amount' => '4000.00',
        'reason' => 'Retiro total a tesorería',
    ], $user->id);

    expect($session->expectedCash())->toBe('0.00');
});

test('an expense in a zero-balance session is rejected', function () {
    $user = User::factory()->create();
    $session = CashSession::factory()->create([
        'user_id' => $user->id,
        'opening_amount' => '0.00',
    ]);

    expect($session->expectedCash())->toBe('0.00');

    expect(fn () => $this->action->handle($session, [
        'type' => 'egreso',
        'amount' => '100.00',
        'reason' => 'Intento de gasto sin fondos',
    ], $user->id))->toThrow(ValidationException::class);
});

test('only open sessions accept cash movements', function () {
    $user = User::factory()->create();
    $session = CashSession::factory()->closed()->create([
        'user_id' => $user->id,
        'opening_amount' => '10000.00',
    ]);

    expect(fn () => $this->action->handle($session, [
        'type' => 'ingreso',
        'amount' => '1000.00',
        'reason' => 'Intento en turno cerrado',
    ], $user->id))->toThrow(ValidationException::class);
});

test('amount must be strictly greater than zero', function () {
    $user = User::factory()->create();
    $session = CashSession::factory()->create([
        'user_id' => $user->id,
        'opening_amount' => '10000.00',
    ]);

    expect(fn () => $this->action->handle($session, [
        'type' => 'ingreso',
        'amount' => '0.00',
        'reason' => 'Importe cero',
    ], $user->id))->toThrow(ValidationException::class);

    expect(fn () => $this->action->handle($session, [
        'type' => 'ingreso',
        'amount' => '-500.00',
        'reason' => 'Importe negativo',
    ], $user->id))->toThrow(ValidationException::class);
});

test('reason is mandatory and cannot be empty or whitespace', function () {
    $user = User::factory()->create();
    $session = CashSession::factory()->create([
        'user_id' => $user->id,
        'opening_amount' => '10000.00',
    ]);

    expect(fn () => $this->action->handle($session, [
        'type' => 'ingreso',
        'amount' => '1000.00',
        'reason' => '',
    ], $user->id))->toThrow(ValidationException::class);

    expect(fn () => $this->action->handle($session, [
        'type' => 'ingreso',
        'amount' => '1000.00',
        'reason' => '   ',
    ], $user->id))->toThrow(ValidationException::class);
});

test('only income and expense manual movement types are permitted', function () {
    $user = User::factory()->create();
    $session = CashSession::factory()->create([
        'user_id' => $user->id,
        'opening_amount' => '10000.00',
    ]);

    expect(fn () => $this->action->handle($session, [
        'type' => 'apertura',
        'amount' => '1000.00',
        'reason' => 'Tipo apertura no permitido',
    ], $user->id))->toThrow(ValidationException::class);

    expect(fn () => $this->action->handle($session, [
        'type' => 'venta',
        'amount' => '1000.00',
        'reason' => 'Tipo venta no permitido',
    ], $user->id))->toThrow(ValidationException::class);
});

test('movements summary correctly breaks down opening, sales cash, income and expense', function () {
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

    $this->action->handle($session, [
        'type' => 'ingreso',
        'amount' => '3000.00',
        'reason' => 'Refuerzo de cambio',
    ], $user->id);

    $this->action->handle($session, [
        'type' => 'egreso',
        'amount' => '1500.00',
        'reason' => 'Gasto de librería',
    ], $user->id);

    $summary = $session->movementsSummary();

    expect($summary['opening_amount'])->toBe('10000.00')
        ->and($summary['income_amount'])->toBe('3000.00')
        ->and($summary['expense_amount'])->toBe('1500.00')
        ->and($summary['expected_cash'])->toBe('11500.00');
});
