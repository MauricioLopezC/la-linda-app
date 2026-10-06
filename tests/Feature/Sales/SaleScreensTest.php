<?php

use App\Enums\Customers\CustomerIdType;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Pricing\VatRate;
use App\Models\Sales\Invoice;
use App\Models\Sales\PointOfSale;
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
            ->where('sales.data.0.cash_session_id', $sale->cash_session_id)
            ->where('sales.data.0.items_count', 2)
            ->where('sales.data.0.total_amount', '150.00')
            ->has('pointsOfSale')
            ->missing('customers'));
});

test('the sales index filters by status, point of sale and date independently and combined', function () {
    $posA = PointOfSale::factory()->create();
    $posB = PointOfSale::factory()->create();

    $sale1 = Sale::factory()->create([
        'point_of_sale_id' => $posA->id,
        'opened_at' => '2026-10-01 10:00:00',
    ]);
    $sale2 = Sale::factory()->discarded()->create([
        'point_of_sale_id' => $posB->id,
        'opened_at' => '2026-10-01 11:00:00',
    ]);
    $sale3 = Sale::factory()->create([
        'point_of_sale_id' => $posA->id,
        'opened_at' => '2026-09-25 10:00:00',
    ]);

    $user = User::factory()->create();

    // Filter by status
    $this->actingAs($user)
        ->get(route('sales.sales.index', ['status' => 'abierta']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sales.data', 2)
            ->where('sales.data.0.id', $sale1->id)
            ->where('sales.data.1.id', $sale3->id));

    // Filter by point of sale
    $this->actingAs($user)
        ->get(route('sales.sales.index', ['point_of_sale_id' => $posA->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sales.data', 2)
            ->where('sales.data.0.id', $sale1->id)
            ->where('sales.data.1.id', $sale3->id));

    // Filter by date
    $this->actingAs($user)
        ->get(route('sales.sales.index', ['date' => '2026-10-01']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sales.data', 2)
            ->where('sales.data.0.id', $sale2->id)
            ->where('sales.data.1.id', $sale1->id));

    // Combined filter
    $this->actingAs($user)
        ->get(route('sales.sales.index', [
            'status' => 'abierta',
            'point_of_sale_id' => $posA->id,
            'date' => '2026-10-01',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sales.data', 1)
            ->where('sales.data.0.id', $sale1->id));
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
            ->where('sale.cash_session_id', $sale->cash_session_id)
            ->where('sale.channel_label', 'Mostrador')
            ->where('sale.is_open', true)
            ->where('sale.accepts_changes', true)
            ->has('sale.items', 1)
            ->has('customers', 3));
});

test('the sale screen marks accepts_changes as false when the cash session is closed', function () {
    $sale = Sale::factory()->closedSession()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('sales.sales.show', $sale))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/sales/show')
            ->where('sale.id', $sale->id)
            ->where('sale.is_open', true)
            ->where('sale.accepts_changes', false));
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

test('the sale screen displays the invoice type and customer tax info', function () {
    $riCustomer = Customer::factory()->responsableInscripto()->create([
        'name' => 'Empresa Test SA',
    ]);
    $saleA = Sale::factory()->create(['customer_id' => $riCustomer->id]);

    $this->actingAs(User::factory()->create())
        ->get(route('sales.sales.show', $saleA))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sale.invoice_type', 'A')
            ->where('sale.invoice_type_label', 'Factura A')
            ->where('sale.customer_tax_condition', 'responsable_inscripto')
            ->where('sale.customer_tax_condition_label', 'IVA Responsable Inscripto')
            ->where('sale.customer_id_number', $riCustomer->formattedIdNumber()));

    $cfCustomer = Customer::factory()->defaultCustomer()->create();
    $saleB = Sale::factory()->create(['customer_id' => $cfCustomer->id]);

    $this->actingAs(User::factory()->create())
        ->get(route('sales.sales.show', $saleB))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sale.invoice_type', 'B')
            ->where('sale.invoice_type_label', 'Factura B')
            ->where('sale.customer_tax_condition', 'consumidor_final'));
});

test('customer search matches name and document and excludes inactive customers', function () {
    $activeCuit = Customer::factory()->create([
        'name' => 'Distribuidora Los Andes',
        'id_type' => CustomerIdType::Cuit,
        'id_number' => '30502793175',
        'is_active' => true,
    ]);

    $activeDni = Customer::factory()->create([
        'name' => 'Pedro Gomez',
        'id_type' => CustomerIdType::Dni,
        'id_number' => '28945612',
        'is_active' => true,
    ]);

    $inactive = Customer::factory()->create([
        'name' => 'Distribuidora Inactiva',
        'id_type' => CustomerIdType::Cuit,
        'id_number' => '30500511849',
        'is_active' => false,
    ]);

    $this->actingAs(User::factory()->create());

    // Search by name
    $this->getJson(route('sales.sales.search-customers', ['search' => 'Distribuidora']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $activeCuit->id);

    // Search by document
    $this->getJson(route('sales.sales.search-customers', ['search' => '28945612']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $activeDni->id);

    // Inactive customer is not found
    $this->getJson(route('sales.sales.search-customers', ['search' => 'Inactiva']))
        ->assertOk()
        ->assertJsonCount(0);
});

test('the sale screen displays the invoice details for a confirmed sale', function () {
    $sale = Sale::factory()->confirmed()->create();
    $invoice = Invoice::factory()->create([
        'sale_id' => $sale->id,
        'point_of_sale_id' => $sale->point_of_sale_id,
        'cash_session_id' => $sale->cash_session_id,
        'customer_id' => $sale->customer_id,
        'user_id' => $sale->user_id,
        'type' => 'B',
        'number' => 1,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sales.sales.show', $sale))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sale.invoice')
            ->where('sale.invoice.id', $invoice->id)
            ->where('sale.invoice.type', 'B')
            ->where('sale.invoice.formatted_number', $invoice->formattedNumber())
            ->where('sale.invoice.voucher_label', $invoice->voucherLabel()));
});

test('the sales index displays invoice formatted number for confirmed sales', function () {
    $sale = Sale::factory()->confirmed()->create(['total_amount' => '1210.00']);
    $invoice = Invoice::factory()->create([
        'sale_id' => $sale->id,
        'point_of_sale_id' => $sale->point_of_sale_id,
        'cash_session_id' => $sale->cash_session_id,
        'customer_id' => $sale->customer_id,
        'user_id' => $sale->user_id,
        'type' => 'B',
        'number' => 1,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sales.sales.index', ['status' => 'confirmada']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sales.data', 1)
            ->where('sales.data.0.id', $sale->id)
            ->where('sales.data.0.invoice_formatted_number', $invoice->formattedNumber()));
});
