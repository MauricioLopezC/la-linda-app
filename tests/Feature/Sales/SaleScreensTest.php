<?php

use App\Models\Sales\Sale;
use App\Models\User;

test('guests are redirected to login', function () {
    $this->get(route('sales.sales.index'))->assertRedirect(route('login'));
});

test('the sales index lists sales with their totals', function () {
    $sale = Sale::factory()->create(['total_amount' => '150.00']);
    Sale::factory()->discarded()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('sales.sales.index', ['status' => 'abierta']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/sales/index')
            ->has('sales.data', 1)
            ->where('sales.data.0.id', $sale->id)
            ->where('sales.data.0.total_amount', '150.00')
            ->has('pointsOfSale')
            ->has('customers'));
});

test('the sale screen shows the header', function () {
    $sale = Sale::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('sales.sales.show', $sale))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/sales/show')
            ->where('sale.id', $sale->id)
            ->where('sale.channel_label', 'Mostrador')
            ->where('sale.is_open', true));
});
