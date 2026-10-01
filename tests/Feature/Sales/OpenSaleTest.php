<?php

use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Customers\Customer;
use App\Models\Sales\CashSession;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\User;

/**
 * Create a user with an open cash session at a new point of sale.
 *
 * @return array{0: User, 1: PointOfSale, 2: CashSession}
 */
function cashierWithOpenSession(): array
{
    $user = User::factory()->create();
    $pointOfSale = PointOfSale::factory()->create();
    $cashSession = CashSession::factory()->create([
        'point_of_sale_id' => $pointOfSale->id,
        'user_id' => $user->id,
    ]);

    return [$user, $pointOfSale, $cashSession];
}

test('opening a sale starts with Consumidor Final, the mostrador channel and the current user', function () {
    [$user, $pointOfSale, $cashSession] = cashierWithOpenSession();
    $consumidorFinal = Customer::factory()->defaultCustomer()->create();

    $response = $this->actingAs($user)->post(route('sales.sales.store'), []);

    $sale = Sale::sole();

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('sales.sales.show', $sale));

    expect($sale->customer_id)->toBe($consumidorFinal->id)
        ->and($sale->channel)->toBe(SaleChannel::Mostrador)
        ->and($sale->status)->toBe(SaleStatus::Open)
        ->and($sale->user_id)->toBe($user->id)
        ->and($sale->point_of_sale_id)->toBe($pointOfSale->id)
        ->and($sale->cash_session_id)->toBe($cashSession->id)
        ->and($sale->opened_at)->not->toBeNull()
        ->and($sale->total_amount)->toBe('0.00');
});

test('forged request payload is ignored and sale is derived exclusively from open session', function () {
    [$user, $pointOfSale, $cashSession] = cashierWithOpenSession();
    $consumidorFinal = Customer::factory()->defaultCustomer()->create();
    $otherCustomer = Customer::factory()->create();
    $otherPointOfSale = PointOfSale::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)->post(route('sales.sales.store'), [
        'point_of_sale_id' => $otherPointOfSale->id,
        'customer_id' => $otherCustomer->id,
        'user_id' => $otherUser->id,
        'channel' => 'online',
        'status' => 'confirmada',
        'total_amount' => '9999.00',
    ])->assertSessionHasNoErrors();

    $sale = Sale::sole();

    expect($sale->customer_id)->toBe($consumidorFinal->id)
        ->and($sale->channel)->toBe(SaleChannel::Mostrador)
        ->and($sale->status)->toBe(SaleStatus::Open)
        ->and($sale->user_id)->toBe($user->id)
        ->and($sale->point_of_sale_id)->toBe($pointOfSale->id)
        ->and($sale->cash_session_id)->toBe($cashSession->id)
        ->and($sale->total_amount)->toBe('0.00');
});

test('a sale cannot be opened without an open cash session', function () {
    Customer::factory()->defaultCustomer()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('sales.sales.store'), [])
        ->assertSessionHasErrors(['cash_session']);

    expect(Sale::count())->toBe(0);
});

test('a sale cannot be opened if the user only has a closed cash session', function () {
    Customer::factory()->defaultCustomer()->create();
    $user = User::factory()->create();
    CashSession::factory()->closed()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('sales.sales.store'), [])
        ->assertSessionHasErrors(['cash_session']);

    expect(Sale::count())->toBe(0);
});

test('a sale cannot be opened if the point of sale was deactivated after opening the session', function () {
    Customer::factory()->defaultCustomer()->create();
    [$user, $pointOfSale] = cashierWithOpenSession();
    $pointOfSale->update(['is_active' => false]);

    $this->actingAs($user)->post(route('sales.sales.store'), [])
        ->assertSessionHasErrors(['point_of_sale']);

    expect(Sale::count())->toBe(0);
});

test('a sale cannot be opened if the default Consumidor Final customer is missing or inactive', function () {
    [$user] = cashierWithOpenSession();

    // No default customer exists
    $this->actingAs($user)->post(route('sales.sales.store'), [])
        ->assertSessionHasErrors(['customer']);

    expect(Sale::count())->toBe(0);

    // Default customer exists but is inactive
    $inactive = Customer::factory()->defaultCustomer()->create(['is_active' => false]);

    $this->actingAs($user)->post(route('sales.sales.store'), [])
        ->assertSessionHasErrors(['customer']);

    expect(Sale::count())->toBe(0);
});

test('an open sale can be discarded with an open session and then rejects changes', function () {
    $sale = Sale::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('sales.sales.discard', $sale))
        ->assertRedirect(route('sales.sales.index'));

    expect($sale->fresh()->status)->toBe(SaleStatus::Discarded);

    $this->post(route('sales.sales.discard', $sale))->assertSessionHasErrors(['sale']);
});

test('an open sale cannot be discarded if its cash session was closed', function () {
    $sale = Sale::factory()->closedSession()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('sales.sales.discard', $sale))
        ->assertSessionHasErrors(['sale']);

    expect($sale->fresh()->status)->toBe(SaleStatus::Open);
});

test('the customer keeps its sales: a customer with sales has associated records', function () {
    $sale = Sale::factory()->create();

    expect($sale->customer->hasAssociatedRecords())->toBeTrue();
});
