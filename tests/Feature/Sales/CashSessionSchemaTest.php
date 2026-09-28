<?php

use App\Enums\Sales\CashMovementType;
use App\Enums\Sales\PaymentMethodKind;
use App\Enums\Sales\SaleStatus;
use App\Models\Sales\CashCount;
use App\Models\Sales\CashMovement;
use App\Models\Sales\CashSession;
use App\Models\Sales\CashSessionClosureLine;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('a point of sale has at most one open cash session', function () {
    $pointOfSale = PointOfSale::factory()->create();
    CashSession::factory()->create(['point_of_sale_id' => $pointOfSale->id]);

    expect(inSavepoint(fn () => CashSession::factory()->create(['point_of_sale_id' => $pointOfSale->id])))
        ->toThrow(QueryException::class);
});

test('a user has at most one open cash session', function () {
    $user = User::factory()->create();
    CashSession::factory()->create(['user_id' => $user->id]);

    expect(inSavepoint(fn () => CashSession::factory()->create(['user_id' => $user->id])))
        ->toThrow(QueryException::class);
});

test('closed sessions do not count against the single open session', function () {
    $pointOfSale = PointOfSale::factory()->create();
    $user = User::factory()->create();
    $attributes = ['point_of_sale_id' => $pointOfSale->id, 'user_id' => $user->id];

    CashSession::factory()->closed()->count(2)->create($attributes);
    CashSession::factory()->create($attributes);

    expect($pointOfSale->cashSessions()->count())->toBe(3)
        ->and(CashSession::query()->open()->count())->toBe(1);
});

test('a session is closed exactly when it has a closing time', function () {
    expect(inSavepoint(fn () => CashSession::factory()->create(['closed_at' => now()])))
        ->toThrow(QueryException::class);

    expect(inSavepoint(fn () => CashSession::factory()->closed()->create(['closed_at' => null])))
        ->toThrow(QueryException::class);
});

test('the opening amount cannot be negative', function () {
    expect(inSavepoint(fn () => CashSession::factory()->create(['opening_amount' => '-1.00'])))
        ->toThrow(QueryException::class);
});

test('a denomination is counted once per session and moment', function () {
    $session = CashSession::factory()->create();
    CashCount::factory()->create(['cash_session_id' => $session->id, 'denomination' => '1000']);
    CashCount::factory()->closing()->create(['cash_session_id' => $session->id, 'denomination' => '1000']);

    expect(inSavepoint(fn () => CashCount::factory()->create(['cash_session_id' => $session->id, 'denomination' => '1000'])))
        ->toThrow(QueryException::class)
        ->and(inSavepoint(fn () => CashCount::factory()->create(['quantity' => -1])))
        ->toThrow(QueryException::class);

    expect($session->openingCounts()->sole()->denomination()->label())->toBe('$1.000');
});

test('a counter sale cannot exist without a cash session, an online sale can', function () {
    expect(inSavepoint(fn () => Sale::factory()->create(['cash_session_id' => null])))
        ->toThrow(QueryException::class);

    $online = Sale::factory()->online()->create();

    expect($online->cash_session_id)->toBeNull();
});

test('a sale is confirmed exactly when it has a confirmation time', function () {
    $confirmed = Sale::factory()->confirmed()->create();

    expect($confirmed->status)->toBe(SaleStatus::Confirmed)
        ->and($confirmed->cashSession->sales()->count())->toBe(1);

    expect(inSavepoint(fn () => Sale::factory()->create(['confirmed_at' => now()])))
        ->toThrow(QueryException::class)
        ->and(inSavepoint(fn () => Sale::factory()->confirmed()->create(['confirmed_at' => null])))
        ->toThrow(QueryException::class);
});

test('a sale movement carries its sale and nothing else does', function () {
    $sale = Sale::factory()->create();
    $movement = CashMovement::factory()->forSale($sale)->create();

    expect($movement->type)->toBe(CashMovementType::Sale)
        ->and($sale->cashMovements()->sole()->id)->toBe($movement->id);

    expect(inSavepoint(fn () => CashMovement::factory()->create(['type' => CashMovementType::Sale, 'reason' => null])))
        ->toThrow(QueryException::class)
        ->and(inSavepoint(fn () => CashMovement::factory()->create(['sale_id' => $sale->id])))
        ->toThrow(QueryException::class);
});

test('an income or expense requires a reason, an opening does not', function () {
    expect(inSavepoint(fn () => CashMovement::factory()->expense()->create(['reason' => null])))
        ->toThrow(QueryException::class);

    expect(CashMovement::factory()->opening()->create()->reason)->toBeNull();
});

test('a movement amount is positive and the tendered cash covers it', function () {
    expect(inSavepoint(fn () => CashMovement::factory()->create(['amount' => '0.00'])))
        ->toThrow(QueryException::class)
        ->and(inSavepoint(fn () => CashMovement::factory()->create(['amount' => '100.00', 'tendered_amount' => '50.00'])))
        ->toThrow(QueryException::class);
});

test('a cash movement is immutable: it has no updated_at column', function () {
    CashMovement::factory()->create();

    expect(Schema::hasColumn('cash_movements', 'updated_at'))->toBeFalse();
});

test('a closure line is unique per payment method and its difference is declared minus expected', function () {
    $line = CashSessionClosureLine::factory()->create([
        'expected_amount' => '1000.00',
        'declared_amount' => '500.00',
        'difference' => '-500.00',
    ]);

    expect(inSavepoint(fn () => CashSessionClosureLine::factory()->create([
        'cash_session_id' => $line->cash_session_id,
        'payment_method_id' => $line->payment_method_id,
    ])))->toThrow(QueryException::class);

    expect(inSavepoint(fn () => CashSessionClosureLine::factory()->create([
        'expected_amount' => '1000.00',
        'declared_amount' => '500.00',
        'difference' => '500.00',
    ])))->toThrow(QueryException::class);
});

test('a payment method kind is restricted and defaults to other', function () {
    $paymentMethod = PaymentMethod::factory()->create();

    expect($paymentMethod->fresh()->kind)->toBe(PaymentMethodKind::Other)
        ->and(inSavepoint(fn () => DB::table('payment_methods')->where('id', $paymentMethod->id)->update(['kind' => 'cheque'])))
        ->toThrow(QueryException::class);
});

test('a payment method with cash movements is in use', function () {
    $paymentMethod = PaymentMethod::factory()->cash()->create();

    expect($paymentMethod->isInUse())->toBeFalse();

    CashMovement::factory()->create(['payment_method_id' => $paymentMethod->id]);

    expect($paymentMethod->isInUse())->toBeTrue();
});
