<?php

use App\Actions\Sales\ConfirmSalePayment;
use App\Models\Catalog\Article;
use App\Models\Ecommerce\WebOrder;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard integration: selling an article makes it top 1 and pending online order appears in operational indicators', function () {
    $this->travelTo('2026-10-15 12:00:00');

    $branch = Branch::factory()->create(['name' => 'Sucursal Central']);
    $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id]);
    $pos = PointOfSale::factory()->create(['warehouse_id' => $warehouse->id]);
    $cash = PaymentMethod::factory()->cash()->create();

    $articleA = Article::factory()->create(['description' => 'Artículo Secundario']);
    $articleB = Article::factory()->create(['description' => 'Artículo Estrella']);

    // Venta previa de artículo A (2 unidades)
    $saleA = Sale::factory()->confirmed()->create([
        'point_of_sale_id' => $pos->id,
        'confirmed_at' => '2026-10-14 10:00:00',
    ]);
    SaleItem::factory()->create([
        'sale_id' => $saleA->id,
        'article_id' => $articleA->id,
        'quantity' => '2.000',
        'line_total' => '2000.00',
    ]);

    // Venta nueva de artículo B (10 unidades) confirmada a través de ConfirmSalePayment
    $saleB = Sale::factory()->create([
        'point_of_sale_id' => $pos->id,
    ]);
    SaleItem::factory()->create([
        'sale_id' => $saleB->id,
        'article_id' => $articleB->id,
        'quantity' => '10.000',
        'unit_price' => '1500.00',
        'line_total' => '15000.00',
    ]);
    $saleB->recalculateTotal();

    app(ConfirmSalePayment::class)->handle($saleB, [
        ['payment_method_id' => $cash->id, 'amount' => '15000.00', 'tendered_amount' => '15000.00'],
    ], $saleB->user);

    // Queda un pedido online pagado pendiente de entrega en depósito
    WebOrder::factory()->paid()->create([
        'number' => 501,
        'items_amount' => '4500.00',
        'total_amount' => '4500.00',
    ]);

    // Un artículo con alerta de stock
    StockBalance::factory()->create([
        'article_id' => $articleA->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '0.000',
    ]);

    // Un proveedor con compras en el período
    $supplier = Supplier::factory()->create(['business_name' => 'Distribuidora Norte']);
    SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '85000.00',
        'issue_date' => '2026-10-10',
        'due_date' => '2026-10-30',
    ]);

    // Consultamos el Dashboard
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('operational.top_articles.0.article_id', $articleB->id)
            ->where('operational.top_articles.0.description', 'Artículo Estrella')
            ->where('operational.top_articles.0.quantity', '10.000')
            ->where('operational.top_articles.0.total_invoiced', '15000.00')
            ->where('operational.top_articles.1.article_id', $articleA->id)
            ->where('operational.pending_web_orders_count', 1)
            ->has('operational.stock_alerts', 2)
            ->where('operational.stock_alerts.0.article_id', $articleB->id)
            ->where('operational.stock_alerts.0.quantity', '-10.000')
            ->where('operational.stock_alerts.0.has_stock_conflict', true)
            ->where('operational.stock_alerts.1.article_id', $articleA->id)
            ->where('operational.stock_alerts.1.quantity', '0.000')
            ->where('operational.stock_alerts.1.has_stock_conflict', false)
            ->has('operational.supplier_ranking', 1)
            ->where('operational.supplier_ranking.0.supplier_id', $supplier->id)
            ->where('operational.supplier_ranking.0.purchases_total', '85000.00')
            ->where('operational.supplier_ranking.0.outstanding_balance', '85000.00')
        );
});
