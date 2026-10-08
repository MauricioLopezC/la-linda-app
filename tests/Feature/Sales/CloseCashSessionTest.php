<?php

use App\Actions\Sales\CloseCashSession;
use App\Actions\Sales\GetCashSessionExpectedTotals;
use App\Actions\Sales\OpenCashSession;
use App\Actions\Sales\OpenSale;
use App\Actions\Sales\RegisterCashMovement;
use App\Enums\Sales\CashCountMoment;
use App\Enums\Sales\CashDenomination;
use App\Enums\Sales\CashSessionStatus;
use App\Models\Sales\CashMovement;
use App\Models\Sales\CashSession;
use App\Models\Sales\CashSessionClosureLine;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\Sale;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A closing count with every denomination at zero, overridden by the given quantities.
 *
 * @param  array<int, int>  $quantities
 * @return array<int, int>
 */
function closingCounts(array $quantities = []): array
{
    $counts = [];

    foreach (CashDenomination::cases() as $denomination) {
        $counts[$denomination->value] = $quantities[$denomination->value] ?? 0;
    }

    return $counts;
}

beforeEach(function () {
    $this->cash = PaymentMethod::factory()->cash()->create(['name' => 'Efectivo']);
    $this->card = PaymentMethod::factory()->card()->create(['name' => 'Tarjeta de débito']);
    $this->user = User::factory()->create();

    /*
     * A shift with a $10.000 opening, a $3.000 cash sale, a $5.000 card sale, a $1.000 income
     * and a $500 expense: $13.500 expected in cash and $5.000 in card.
     */
    $this->session = CashSession::factory()->create([
        'user_id' => $this->user->id,
        'opening_amount' => '10000.00',
    ]);
    $sale = Sale::factory()->confirmed()->create([
        'point_of_sale_id' => $this->session->point_of_sale_id,
        'user_id' => $this->user->id,
        'total_amount' => '8000.00',
    ]);

    $movement = ['cash_session_id' => $this->session->id, 'user_id' => $this->user->id];
    CashMovement::factory()->opening()->create([...$movement, 'payment_method_id' => $this->cash->id, 'amount' => '10000.00']);
    CashMovement::factory()->forSale($sale)->create([...$movement, 'payment_method_id' => $this->cash->id, 'amount' => '3000.00']);
    CashMovement::factory()->forSale($sale)->create([...$movement, 'payment_method_id' => $this->card->id, 'amount' => '5000.00']);
    CashMovement::factory()->create([...$movement, 'payment_method_id' => $this->cash->id, 'amount' => '1000.00']);
    CashMovement::factory()->expense()->create([...$movement, 'payment_method_id' => $this->cash->id, 'amount' => '500.00']);

    $this->action = app(CloseCashSession::class);
});

test('the expected amount of each payment method is the signed sum of its movements', function () {
    $totals = app(GetCashSessionExpectedTotals::class)->handle($this->session);

    expect($totals)->toHaveCount(2)
        ->and($totals[0]['payment_method']->id)->toBe($this->cash->id)
        ->and($totals[0]['opening_amount'])->toBe('10000.00')
        ->and($totals[0]['sales_amount'])->toBe('3000.00')
        ->and($totals[0]['income_amount'])->toBe('1000.00')
        ->and($totals[0]['expense_amount'])->toBe('500.00')
        ->and($totals[0]['expected_amount'])->toBe('13500.00')
        ->and($totals[1]['payment_method']->id)->toBe($this->card->id)
        ->and($totals[1]['expected_amount'])->toBe('5000.00')
        ->and($this->session->expectedCash())->toBe('13500.00');
});

test('the cash line is present even without cash movements', function () {
    $session = CashSession::factory()->create();

    $totals = app(GetCashSessionExpectedTotals::class)->handle($session);

    expect($totals)->toHaveCount(1)
        ->and($totals[0]['payment_method']->id)->toBe($this->cash->id)
        ->and($totals[0]['expected_amount'])->toBe('0.00');
});

test('a balanced closing records the count, the lines and closes the session', function () {
    $session = $this->action->handle($this->session, [
        'counts' => closingCounts([10000 => 1, 2000 => 1, 1000 => 1, 500 => 1]),
        'declarations' => [$this->card->id => ['declared_amount' => '5000.00', 'batch_reference' => '0042']],
    ]);

    expect($session->status)->toBe(CashSessionStatus::Closed)
        ->and($session->closed_at)->not->toBeNull()
        ->and($session->closing_notes)->toBeNull()
        ->and($session->counts()->where('moment', CashCountMoment::Closing)->count())->toBe(count(CashDenomination::cases()));

    $lines = $session->closureLines()->orderBy('id')->get();

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->only(['payment_method_id', 'expected_amount', 'declared_amount', 'difference', 'batch_reference']))->toBe([
            'payment_method_id' => $this->cash->id,
            'expected_amount' => '13500.00',
            'declared_amount' => '13500.00',
            'difference' => '0.00',
            'batch_reference' => null,
        ])
        ->and($lines[1]->only(['payment_method_id', 'expected_amount', 'declared_amount', 'difference', 'batch_reference']))->toBe([
            'payment_method_id' => $this->card->id,
            'expected_amount' => '5000.00',
            'declared_amount' => '5000.00',
            'difference' => '0.00',
            'batch_reference' => '0042',
        ]);
});

test('acceptance verification: a $500 cash shortage with the card balanced', function () {
    $session = $this->action->handle($this->session, [
        'counts' => closingCounts([10000 => 1, 2000 => 1, 1000 => 1]),
        'declarations' => [$this->card->id => ['declared_amount' => '5000', 'batch_reference' => '0042']],
        'closing_notes' => 'Faltan $500, se revisa con el encargado.',
    ]);

    $lines = $session->closureLines()->get()->keyBy('payment_method_id');

    expect($lines[$this->cash->id]->declared_amount)->toBe('13000.00')
        ->and($lines[$this->cash->id]->difference)->toBe('-500.00')
        ->and($lines[$this->card->id]->difference)->toBe('0.00')
        ->and($session->closing_notes)->toBe('Faltan $500, se revisa con el encargado.');

    expect(fn () => app(OpenSale::class)->handle($this->user))->toThrow(ValidationException::class);
});

test('a surplus is a positive difference', function () {
    $session = $this->action->handle($this->session, [
        'counts' => closingCounts([10000 => 1, 2000 => 2]),
        'declarations' => [$this->card->id => ['declared_amount' => '5000', 'batch_reference' => '0042']],
        'closing_notes' => 'Sobrante sin identificar.',
    ]);

    expect($session->closureLines()->where('payment_method_id', $this->cash->id)->value('difference'))->toBe('500.00');
});

test('a difference requires the closing notes', function () {
    expect(fn () => $this->action->handle($this->session, [
        'counts' => closingCounts([10000 => 1]),
        'declarations' => [$this->card->id => ['declared_amount' => '5000', 'batch_reference' => '0042']],
        'closing_notes' => '   ',
    ]))->toThrow(ValidationException::class, 'observación');

    expect($this->session->fresh()->isOpen())->toBeTrue()
        ->and(CashSessionClosureLine::count())->toBe(0);
});

test('a card requires the POSNET batch number', function () {
    expect(fn () => $this->action->handle($this->session, [
        'counts' => closingCounts([10000 => 1, 2000 => 1, 1000 => 1, 500 => 1]),
        'declarations' => [$this->card->id => ['declared_amount' => '5000']],
    ]))->toThrow(ValidationException::class, 'número de lote');
});

test('every non-cash payment method with movements must be declared', function () {
    expect(fn () => $this->action->handle($this->session, [
        'counts' => closingCounts([10000 => 1, 2000 => 1, 1000 => 1, 500 => 1]),
    ]))->toThrow(ValidationException::class, 'Tarjeta de débito');
});

test('open sales block the closing', function () {
    Sale::factory()->create([
        'point_of_sale_id' => $this->session->point_of_sale_id,
        'user_id' => $this->user->id,
    ]);

    expect(fn () => $this->action->handle($this->session, [
        'counts' => closingCounts([10000 => 1, 2000 => 1, 1000 => 1, 500 => 1]),
        'declarations' => [$this->card->id => ['declared_amount' => '5000', 'batch_reference' => '0042']],
    ]))->toThrow(ValidationException::class, '1 venta abierta');
});

test('a closed session cannot be closed again nor accept movements', function () {
    $this->session->update(['status' => CashSessionStatus::Closed, 'closed_at' => now()]);

    expect(fn () => $this->action->handle($this->session, ['counts' => closingCounts()]))
        ->toThrow(ValidationException::class, 'ya está cerrado')
        ->and(fn () => app(RegisterCashMovement::class)->handle($this->session, [
            'type' => 'ingreso',
            'amount' => '100',
            'reason' => 'Refuerzo',
        ], $this->user->id))->toThrow(ValidationException::class);
});

test('after closing, the point of sale and the cashier can open a new session', function () {
    $this->action->handle($this->session, [
        'counts' => closingCounts([10000 => 1, 2000 => 1, 1000 => 1, 500 => 1]),
        'declarations' => [$this->card->id => ['declared_amount' => '5000', 'batch_reference' => '0042']],
    ]);

    $newSession = app(OpenCashSession::class)->handle([
        'point_of_sale_id' => $this->session->point_of_sale_id,
        'counts' => closingCounts(),
    ], $this->user->id);

    expect($newSession->isOpen())->toBeTrue()
        ->and($newSession->id)->not->toBe($this->session->id);
});

test('the cashier closes the session over HTTP and sees its closing summary', function () {
    $this->actingAs($this->user)
        ->get(route('sales.cash-sessions.closing.create', $this->session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sales/cash-sessions/close')
            ->where('openSalesCount', 0)
            ->has('denominations', count(CashDenomination::cases()))
            ->has('cashSession.payment_methods', 2)
            ->where('cashSession.payment_methods.1.requires_batch_reference', true)
        );

    $this->actingAs($this->user)
        ->post(route('sales.cash-sessions.closing.store', $this->session), [
            'counts' => closingCounts([10000 => 1, 2000 => 1, 1000 => 1]),
            'declarations' => [$this->card->id => ['declared_amount' => '5000', 'batch_reference' => '0042']],
            'closing_notes' => 'Faltan $500.',
        ])
        ->assertRedirect(route('sales.cash-sessions.show', $this->session));

    $this->actingAs($this->user)
        ->get(route('sales.cash-sessions.show', $this->session))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cashSession.is_open', false)
            ->has('cashSession.closure_lines', 2)
            ->where('cashSession.closure_lines.0.difference', '-500.00')
            ->where('cashSession.closure_lines.1.batch_reference', '0042')
            ->has('cashSession.closing_counts', count(CashDenomination::cases()))
        );
});

test('the closing count is blind: the form gets no expected amounts', function () {
    $this->actingAs($this->user)
        ->get(route('sales.cash-sessions.closing.create', $this->session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sales/cash-sessions/close')
            ->missing('cashSession.expected_totals')
            ->missing('cashSession.totals')
            ->missing('cashSession.movements')
            ->has('cashSession.payment_methods.0', fn (Assert $method) => $method
                ->where('payment_method_name', 'Efectivo')
                ->where('is_cash', true)
                ->missing('expected_amount')
                ->etc()
            )
        );
});

test('only the cashier who opened the session can close it', function () {
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->get(route('sales.cash-sessions.closing.create', $this->session))
        ->assertForbidden();

    $this->actingAs($otherUser)
        ->post(route('sales.cash-sessions.closing.store', $this->session), [
            'counts' => closingCounts(),
        ])
        ->assertForbidden();

    expect($this->session->fresh()->isOpen())->toBeTrue();
});

test('the closing form of a closed session redirects to its summary', function () {
    $this->session->update(['status' => CashSessionStatus::Closed, 'closed_at' => now()]);

    $this->actingAs($this->user)
        ->get(route('sales.cash-sessions.closing.create', $this->session))
        ->assertRedirect(route('sales.cash-sessions.show', $this->session));
});

test('the closing summary of a closed session downloads as a PDF', function () {
    $this->action->handle($this->session, [
        'counts' => closingCounts([10000 => 1, 2000 => 1, 1000 => 1]),
        'declarations' => [$this->card->id => ['declared_amount' => '5000', 'batch_reference' => '0042']],
        'closing_notes' => 'Faltan $500.',
    ]);

    $this->actingAs($this->user)
        ->get(route('sales.cash-sessions.closing.pdf', $this->session))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('an open session has no closing PDF', function () {
    $this->actingAs($this->user)
        ->get(route('sales.cash-sessions.closing.pdf', $this->session))
        ->assertNotFound();
});
