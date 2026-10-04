<?php

use App\Actions\Sales\ConfirmSalePayment;
use App\Enums\Sales\CashMovementType;
use App\Enums\Sales\CashSessionStatus;
use App\Enums\Sales\PaymentMethodKind;
use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Events\Sales\SaleConfirmed;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Sales\CashMovement;
use App\Models\Sales\CashSession;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/**
 * Setup a sale with an open session, point of sale, cashier user, and given total amount.
 *
 * @return array{0: User, 1: Sale, 2: CashSession, 3: PointOfSale}
 */
function createSaleWithItems(string $totalAmount = '8500.00'): array
{
    $user = User::factory()->create();
    $pointOfSale = PointOfSale::factory()->create();
    $cashSession = CashSession::factory()->create([
        'point_of_sale_id' => $pointOfSale->id,
        'user_id' => $user->id,
        'status' => CashSessionStatus::Open,
    ]);

    $sale = Sale::factory()->create([
        'point_of_sale_id' => $pointOfSale->id,
        'user_id' => $user->id,
        'cash_session_id' => $cashSession->id,
        'channel' => SaleChannel::Mostrador,
        'status' => SaleStatus::Open,
        'total_amount' => $totalAmount,
    ]);

    // Create an article and a sale item matching the total
    $article = Article::factory()->create();
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'article_id' => $article->id,
        'quantity' => '1.000',
        'unit_price' => $totalAmount,
        'line_total' => $totalAmount,
    ]);

    $sale->recalculateTotal();

    return [$user, $sale, $cashSession, $pointOfSale];
}

test('guest cannot confirm sale payment', function () {
    $sale = Sale::factory()->create();

    $this->post(route('sales.sales.confirm-payment', $sale), [
        'payments' => [],
    ])->assertRedirect(route('login'));
});

test('PO scenario: $8.500 paid with $5.000 debit and $10.000 cash tenders $6.500 change with two cash movements of $5.000 and $3.500', function () {
    Event::fake([SaleConfirmed::class]);

    [$user, $sale, $cashSession] = createSaleWithItems('8500.00');

    $debitMethod = PaymentMethod::factory()->create([
        'name' => 'Tarjeta Débito',
        'kind' => PaymentMethodKind::Card,
        'is_active' => true,
    ]);

    $cashMethod = PaymentMethod::factory()->create([
        'name' => 'Efectivo',
        'kind' => PaymentMethodKind::Cash,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->post(route('sales.sales.confirm-payment', $sale), [
        'payments' => [
            [
                'payment_method_id' => $debitMethod->id,
                'amount' => 5000.00,
                'tendered_amount' => null,
            ],
            [
                'payment_method_id' => $cashMethod->id,
                'amount' => 3500.00,
                'tendered_amount' => 10000.00,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('sales.sales.show', $sale));

    // Verify sale status and immutability
    $freshSale = $sale->fresh();
    expect($freshSale->status)->toBe(SaleStatus::Confirmed)
        ->and($freshSale->confirmed_at)->not->toBeNull()
        ->and($freshSale->isOpen())->toBeFalse()
        ->and($freshSale->acceptsChanges())->toBeFalse();

    // Verify exactly 2 cash movements created in this session
    $movements = CashMovement::query()
        ->where('cash_session_id', $cashSession->id)
        ->where('sale_id', $sale->id)
        ->orderBy('id')
        ->get();

    expect($movements)->toHaveCount(2);

    // Movement 1: Card ($5.000)
    $cardMovement = $movements->firstWhere('payment_method_id', $debitMethod->id);
    expect($cardMovement)->not->toBeNull()
        ->and($cardMovement->type)->toBe(CashMovementType::Sale)
        ->and((float) $cardMovement->amount)->toBe(5000.00)
        ->and($cardMovement->tendered_amount)->toBeNull()
        ->and($cardMovement->user_id)->toBe($user->id);

    // Movement 2: Cash ($3.500 sale amount recorded, $10.000 tendered recorded)
    $cashMovement = $movements->firstWhere('payment_method_id', $cashMethod->id);
    expect($cashMovement)->not->toBeNull()
        ->and($cashMovement->type)->toBe(CashMovementType::Sale)
        ->and((float) $cashMovement->amount)->toBe(3500.00)
        ->and((float) $cashMovement->tendered_amount)->toBe(10000.00)
        ->and($cashMovement->changeAmount())->toBe(6500.00)
        ->and($cashMovement->user_id)->toBe($user->id);

    // Verify SaleConfirmed event was dispatched
    Event::assertDispatched(SaleConfirmed::class, function (SaleConfirmed $event) use ($sale, $user) {
        return $event->sale->id === $sale->id && $event->user->id === $user->id;
    });
});

test('simple cash payment without change creates cash movement with exact amount', function () {
    [$user, $sale, $cashSession] = createSaleWithItems('1500.00');

    $cashMethod = PaymentMethod::factory()->create([
        'name' => 'Efectivo',
        'kind' => PaymentMethodKind::Cash,
        'is_active' => true,
    ]);

    $this->actingAs($user)->post(route('sales.sales.confirm-payment', $sale), [
        'payments' => [
            [
                'payment_method_id' => $cashMethod->id,
                'amount' => 1500.00,
                'tendered_amount' => 1500.00,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $movement = CashMovement::query()
        ->where('cash_session_id', $cashSession->id)
        ->where('sale_id', $sale->id)
        ->sole();

    expect((float) $movement->amount)->toBe(1500.00)
        ->and((float) $movement->tendered_amount)->toBe(1500.00)
        ->and($movement->changeAmount())->toBe(0.0)
        ->and($sale->fresh()->status)->toBe(SaleStatus::Confirmed);
});

test('card payment does not require coupon code or batch number', function () {
    [$user, $sale, $cashSession] = createSaleWithItems('2000.00');

    $cardMethod = PaymentMethod::factory()->create([
        'name' => 'Tarjeta Crédito',
        'kind' => PaymentMethodKind::Card,
        'is_active' => true,
    ]);

    $this->actingAs($user)->post(route('sales.sales.confirm-payment', $sale), [
        'payments' => [
            [
                'payment_method_id' => $cardMethod->id,
                'amount' => 2000.00,
                'tendered_amount' => null,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $movement = CashMovement::query()
        ->where('cash_session_id', $cashSession->id)
        ->where('sale_id', $sale->id)
        ->sole();

    expect((float) $movement->amount)->toBe(2000.00)
        ->and($movement->tendered_amount)->toBeNull()
        ->and($sale->fresh()->status)->toBe(SaleStatus::Confirmed);
});

test('payment fails when payment amounts sum does not match sale total', function () {
    [$user, $sale] = createSaleWithItems('1000.00');

    $cashMethod = PaymentMethod::factory()->create([
        'kind' => PaymentMethodKind::Cash,
        'is_active' => true,
    ]);

    // Less than total
    $this->actingAs($user)->post(route('sales.sales.confirm-payment', $sale), [
        'payments' => [
            [
                'payment_method_id' => $cashMethod->id,
                'amount' => 900.00,
            ],
        ],
    ])->assertSessionHasErrors(['payments']);

    // More than total
    $this->actingAs($user)->post(route('sales.sales.confirm-payment', $sale), [
        'payments' => [
            [
                'payment_method_id' => $cashMethod->id,
                'amount' => 1100.00,
            ],
        ],
    ])->assertSessionHasErrors(['payments']);

    expect($sale->fresh()->status)->toBe(SaleStatus::Open)
        ->and(CashMovement::count())->toBe(0);
});

test('non-cash payment cannot receive tendered amount greater than amount', function () {
    [$user, $sale] = createSaleWithItems('1000.00');

    $cardMethod = PaymentMethod::factory()->create([
        'kind' => PaymentMethodKind::Card,
        'is_active' => true,
    ]);

    $this->actingAs($user)->post(route('sales.sales.confirm-payment', $sale), [
        'payments' => [
            [
                'payment_method_id' => $cardMethod->id,
                'amount' => 1000.00,
                'tendered_amount' => 1500.00,
            ],
        ],
    ])->assertSessionHasErrors(['payments.0.tendered_amount']);

    expect($sale->fresh()->status)->toBe(SaleStatus::Open)
        ->and(CashMovement::count())->toBe(0);
});

test('cash payment cannot have tendered amount less than amount', function () {
    [$user, $sale] = createSaleWithItems('1000.00');

    $cashMethod = PaymentMethod::factory()->create([
        'kind' => PaymentMethodKind::Cash,
        'is_active' => true,
    ]);

    $this->actingAs($user)->post(route('sales.sales.confirm-payment', $sale), [
        'payments' => [
            [
                'payment_method_id' => $cashMethod->id,
                'amount' => 1000.00,
                'tendered_amount' => 800.00,
            ],
        ],
    ])->assertSessionHasErrors(['payments.0.tendered_amount']);

    expect($sale->fresh()->status)->toBe(SaleStatus::Open)
        ->and(CashMovement::count())->toBe(0);
});

test('payment fails with inactive payment method', function () {
    [$user, $sale] = createSaleWithItems('1000.00');

    $inactiveMethod = PaymentMethod::factory()->create([
        'kind' => PaymentMethodKind::Cash,
        'is_active' => false,
    ]);

    $this->actingAs($user)->post(route('sales.sales.confirm-payment', $sale), [
        'payments' => [
            [
                'payment_method_id' => $inactiveMethod->id,
                'amount' => 1000.00,
            ],
        ],
    ])->assertSessionHasErrors(['payments.0.payment_method_id']);

    expect($sale->fresh()->status)->toBe(SaleStatus::Open);
});

test('payment fails when payments list is empty', function () {
    [$user, $sale] = createSaleWithItems('1000.00');

    $this->actingAs($user)->post(route('sales.sales.confirm-payment', $sale), [
        'payments' => [],
    ])->assertSessionHasErrors(['payments']);

    expect($sale->fresh()->status)->toBe(SaleStatus::Open);
});

test('cannot confirm sale without items', function () {
    $user = User::factory()->create();
    $pointOfSale = PointOfSale::factory()->create();
    $cashSession = CashSession::factory()->create([
        'point_of_sale_id' => $pointOfSale->id,
        'user_id' => $user->id,
    ]);

    $sale = Sale::factory()->create([
        'point_of_sale_id' => $pointOfSale->id,
        'user_id' => $user->id,
        'cash_session_id' => $cashSession->id,
        'total_amount' => '0.00',
    ]);

    $cashMethod = PaymentMethod::factory()->create(['is_active' => true]);

    $this->actingAs($user)->post(route('sales.sales.confirm-payment', $sale), [
        'payments' => [
            [
                'payment_method_id' => $cashMethod->id,
                'amount' => 0.00,
            ],
        ],
    ])->assertSessionHasErrors();

    expect($sale->fresh()->status)->toBe(SaleStatus::Open);
});

test('cannot confirm sale already confirmed', function () {
    [$user, $sale] = createSaleWithItems('1000.00');
    $sale->update([
        'status' => SaleStatus::Confirmed,
        'confirmed_at' => now(),
    ]);

    $cashMethod = PaymentMethod::factory()->create(['is_active' => true]);

    $this->actingAs($user)->post(route('sales.sales.confirm-payment', $sale), [
        'payments' => [
            [
                'payment_method_id' => $cashMethod->id,
                'amount' => 1000.00,
            ],
        ],
    ])->assertSessionHasErrors(['sale']);
});

test('cannot confirm sale discarded', function () {
    [$user, $sale] = createSaleWithItems('1000.00');
    $sale->update(['status' => SaleStatus::Discarded]);

    $cashMethod = PaymentMethod::factory()->create(['is_active' => true]);

    $this->actingAs($user)->post(route('sales.sales.confirm-payment', $sale), [
        'payments' => [
            [
                'payment_method_id' => $cashMethod->id,
                'amount' => 1000.00,
            ],
        ],
    ])->assertSessionHasErrors(['sale']);
});

test('cannot confirm sale when cash session is closed', function () {
    [$user, $sale, $cashSession] = createSaleWithItems('1000.00');
    $cashSession->update(['status' => CashSessionStatus::Closed, 'closed_at' => now()]);

    $cashMethod = PaymentMethod::factory()->create(['is_active' => true]);

    $this->actingAs($user)->post(route('sales.sales.confirm-payment', $sale), [
        'payments' => [
            [
                'payment_method_id' => $cashMethod->id,
                'amount' => 1000.00,
            ],
        ],
    ])->assertSessionHasErrors(['sale']);

    expect($sale->fresh()->status)->toBe(SaleStatus::Open);
});

test('confirmed sale is strictly immutable: cannot add, update, remove items, change customer, or discard', function () {
    [$user, $sale] = createSaleWithItems('1000.00');

    $cashMethod = PaymentMethod::factory()->create([
        'kind' => PaymentMethodKind::Cash,
        'is_active' => true,
    ]);

    $this->actingAs($user)->post(route('sales.sales.confirm-payment', $sale), [
        'payments' => [
            [
                'payment_method_id' => $cashMethod->id,
                'amount' => 1000.00,
            ],
        ],
    ])->assertSessionHasNoErrors();

    expect($sale->fresh()->status)->toBe(SaleStatus::Confirmed);

    // Attempt to discard
    $this->actingAs($user)->post(route('sales.sales.discard', $sale))
        ->assertSessionHasErrors(['sale']);

    // Attempt to change customer
    $newCustomer = Customer::factory()->create();
    $this->actingAs($user)->patch(route('sales.sales.customer.update', $sale), [
        'customer_id' => $newCustomer->id,
    ])->assertSessionHasErrors(['customer_id']);

    // Attempt to add item
    $newArticle = Article::factory()->create();
    $this->actingAs($user)->post(route('sales.sales.items.store', $sale), [
        'article_id' => $newArticle->id,
    ])->assertSessionHasErrors();

    // Attempt to delete item
    $existingItem = $sale->items()->first();
    $this->actingAs($user)->delete(route('sales.sales.items.destroy', [$sale, $existingItem]))
        ->assertSessionHasErrors();

    expect($sale->fresh()->status)->toBe(SaleStatus::Confirmed)
        ->and($sale->fresh()->customer_id)->not->toBe($newCustomer->id);
});

test('confirmation is atomic: rolls back if downstream hook fails', function () {
    [$user, $sale] = createSaleWithItems('1000.00');

    $cashMethod = PaymentMethod::factory()->create([
        'kind' => PaymentMethodKind::Cash,
        'is_active' => true,
    ]);

    // Listen to SaleConfirmed event and throw an exception to simulate downstream failure
    Event::listen(SaleConfirmed::class, function () {
        throw new RuntimeException('Downstream service failed (e.g. invoice or stock error)');
    });

    try {
        app(ConfirmSalePayment::class)->handle($sale, [
            [
                'payment_method_id' => $cashMethod->id,
                'amount' => 1000.00,
                'tendered_amount' => null,
            ],
        ], $user);
        $this->fail('Expected exception was not thrown');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('Downstream service failed');
    }

    // Verify everything was rolled back!
    expect($sale->fresh()->status)->toBe(SaleStatus::Open)
        ->and($sale->fresh()->confirmed_at)->toBeNull()
        ->and(CashMovement::count())->toBe(0);
});
