<?php

use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Pricing\VatRate;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\User;

test('guests are redirected to login', function () {
    $this->get(route('sales.sales.index'))->assertRedirect(route('login'));
});

test('the sales index lists sales with their totals and line counts', function () {
    $sale = Sale::factory()->create(['total_amount' => '150.00']);
    SaleItem::factory()->count(2)->create(['sale_id' => $sale->id]);
    Sale::factory()->discarded()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('sales.sales.index', ['status' => 'abierta']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/sales/index')
            ->has('sales.data', 1)
            ->where('sales.data.0.id', $sale->id)
            ->where('sales.data.0.items_count', 2)
            ->where('sales.data.0.total_amount', '150.00')
            ->has('pointsOfSale')
            ->missing('customers'));
});

test('the sale screen shows the header, the lines and the customers to choose from', function () {
    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id]);
    Customer::factory()->count(2)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('sales.sales.show', $sale))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/sales/show')
            ->where('sale.id', $sale->id)
            ->where('sale.channel_label', 'Mostrador')
            ->where('sale.is_open', true)
            ->has('sale.items', 1)
            ->has('customers', 3));
});

test('the sale screen displays the VAT breakdown and net and VAT amounts for multiple rates', function () {
    $sale = Sale::factory()->create();
    $vat21 = VatRate::factory()->create(['description' => 'IVA General 21%', 'percentage' => 21.0]);
    $vat105 = VatRate::factory()->create(['description' => 'IVA Reducido 10.5%', 'percentage' => 10.5]);

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'quantity' => '1.000',
        'unit_price' => '121.00',
        'line_total' => '121.00',
        'vat_rate_id' => $vat21->id,
        'vat_rate' => '21.00',
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'quantity' => '1.000',
        'unit_price' => '110.50',
        'line_total' => '110.50',
        'vat_rate_id' => $vat105->id,
        'vat_rate' => '10.50',
    ]);
    $sale->recalculateTotal();

    $this->actingAs(User::factory()->create())
        ->get(route('sales.sales.show', $sale))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/sales/show')
            ->where('sale.id', $sale->id)
            ->where('sale.total_amount', '231.50')
            ->where('sale.net_amount', '200.00')
            ->where('sale.vat_amount', '31.50')
            ->has('sale.vat_breakdown', 2)
            ->where('sale.vat_breakdown.0.vat_rate', '21.00')
            ->where('sale.vat_breakdown.0.net_amount', '100.00')
            ->where('sale.vat_breakdown.0.vat_amount', '21.00')
            ->where('sale.vat_breakdown.0.total_amount', '121.00')
            ->where('sale.vat_breakdown.1.vat_rate', '10.50')
            ->where('sale.vat_breakdown.1.net_amount', '100.00')
            ->where('sale.vat_breakdown.1.vat_amount', '10.50')
            ->where('sale.vat_breakdown.1.total_amount', '110.50')
            ->has('sale.items', 2)
            ->where('sale.items.0.vat_rate', '21.00')
            ->where('sale.items.0.net_amount', '100.00')
            ->where('sale.items.0.vat_amount', '21.00'));
});

test('article search matches description, internal code and barcode', function () {
    $article = Article::factory()->create([
        'description' => 'Yerba mate suave',
        'barcode' => '7790387010016',
    ]);

    $this->actingAs(User::factory()->create());

    $this->getJson(route('sales.sales.search-articles', ['search' => 'yerba']))
        ->assertOk()
        ->assertJsonPath('0.id', $article->id);

    $this->getJson(route('sales.sales.search-articles', ['search' => '7790387']))
        ->assertJsonPath('0.id', $article->id);
});
