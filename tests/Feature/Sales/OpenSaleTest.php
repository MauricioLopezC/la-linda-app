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

    $response = $this->actingAs($user)->post(route('sales.sales.store'), [
        'point_of_sale_id' => $pointOfSale->id,
    ]);

    $sale = Sale::sole();

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('sales.sales.show', $sale));

    expect($sale->customer_id)->toBe($consumidorFinal->id)
        ->and($sale->channel)->toBe(SaleChannel::Mostrador)
        ->and($sale->status)->toBe(SaleStatus::Open)
        ->and($sale->user_id)->toBe($user->id)
        ->and($sale->cash_session_id)->toBe($cashSession->id)
        ->and($sale->opened_at)->not->toBeNull()
        ->and($sale->total_amount)->toBe('0.00');
});

test('a sale can be opened for a specific customer', function () {
    Customer::factory()->defaultCustomer()->create();
    $customer = Customer::factory()->create();
    [$user, $pointOfSale] = cashierWithOpenSession();

    $this->actingAs($user)->post(route('sales.sales.store'), [
        'point_of_sale_id' => $pointOfSale->id,
        'customer_id' => $customer->id,
    ])->assertSessionHasNoErrors();

    expect(Sale::sole()->customer_id)->toBe($customer->id);
});

test('the channel is always mostrador even if the request asks for online', function () {
    Customer::factory()->defaultCustomer()->create();
    [$user, $pointOfSale] = cashierWithOpenSession();

    $this->actingAs($user)->post(route('sales.sales.store'), [
        'point_of_sale_id' => $pointOfSale->id,
        'channel' => 'online',
    ])->assertSessionHasNoErrors();

    expect(Sale::sole()->channel)->toBe(SaleChannel::Mostrador);
});

test('a sale cannot be opened at an inactive point of sale', function () {
    Customer::factory()->defaultCustomer()->create();
    $pointOfSale = PointOfSale::factory()->create(['is_active' => false]);

    $this->actingAs(User::factory()->create())->post(route('sales.sales.store'), [
        'point_of_sale_id' => $pointOfSale->id,
    ])->assertSessionHasErrors(['point_of_sale_id']);

    expect(Sale::count())->toBe(0);
});

test('a sale cannot be opened for an inactive customer', function () {
    $customer = Customer::factory()->create(['is_active' => false]);
    [$user, $pointOfSale] = cashierWithOpenSession();

    $this->actingAs($user)->post(route('sales.sales.store'), [
        'point_of_sale_id' => $pointOfSale->id,
        'customer_id' => $customer->id,
    ])->assertSessionHasErrors(['customer_id']);
});

test('a sale cannot be opened without an open cash session at that point of sale', function () {
    Customer::factory()->defaultCustomer()->create();
    [$user] = cashierWithOpenSession();
    $otherPointOfSale = PointOfSale::factory()->create();
    CashSession::factory()->closed()->create([
        'point_of_sale_id' => $otherPointOfSale->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)->post(route('sales.sales.store'), [
        'point_of_sale_id' => $otherPointOfSale->id,
    ])->assertSessionHasErrors(['point_of_sale_id']);

    expect(Sale::count())->toBe(0);
});

test('an open sale can be discarded and then rejects changes', function () {
    $sale = Sale::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('sales.sales.discard', $sale))
        ->assertRedirect(route('sales.sales.index'));

    expect($sale->fresh()->status)->toBe(SaleStatus::Discarded);

    $this->post(route('sales.sales.discard', $sale))->assertSessionHasErrors(['sale']);
});

test('the customer keeps its sales: a customer with sales has associated records', function () {
    $sale = Sale::factory()->create();

    expect($sale->customer->hasAssociatedRecords())->toBeTrue();
});
