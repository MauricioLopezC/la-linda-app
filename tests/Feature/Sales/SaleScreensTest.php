<?php

use App\Models\Catalog\Article;
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
            ->has('customers'));
});

test('the sale screen shows the header and the lines', function () {
    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id]);

    $this->actingAs(User::factory()->create())
        ->get(route('sales.sales.show', $sale))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/sales/show')
            ->where('sale.id', $sale->id)
            ->where('sale.channel_label', 'Mostrador')
            ->where('sale.is_open', true)
            ->has('sale.items', 1));
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
