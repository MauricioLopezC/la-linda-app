<?php

use App\Actions\Purchasing\AssociateCreditNoteToInvoice;
use App\Actions\Reporting\GetDashboardOperationalIndicators;
use App\Enums\Catalog\ArticleStatus;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Catalog\Article;
use App\Models\Ecommerce\WebOrder;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\Purchasing\PaymentOrder;
use App\Models\Purchasing\PaymentOrderItem;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\User;

test('top articles ranks confirmed sales by quantity and invoiced total and respects filters', function () {
    $this->travelTo('2026-10-15 12:00:00');

    $branchA = Branch::factory()->create(['name' => 'Sucursal A']);
    $branchB = Branch::factory()->create(['name' => 'Sucursal B']);

    $warehouseA = Warehouse::factory()->create(['branch_id' => $branchA->id]);
    $warehouseB = Warehouse::factory()->create(['branch_id' => $branchB->id]);

    $posA = PointOfSale::factory()->create(['warehouse_id' => $warehouseA->id]);
    $posB = PointOfSale::factory()->create(['warehouse_id' => $warehouseB->id]);

    $article1 = Article::factory()->create(['description' => 'Yerba 1kg']);
    $article2 = Article::factory()->create(['description' => 'Azúcar 1kg']);
    $article3 = Article::factory()->create(['description' => 'Fideos 500g']);

    // Venta confirmada en branchA dentro del rango
    $sale1 = Sale::factory()->confirmed()->create([
        'point_of_sale_id' => $posA->id,
        'channel' => SaleChannel::Mostrador,
        'confirmed_at' => '2026-10-10 14:00:00',
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale1->id,
        'article_id' => $article1->id,
        'quantity' => '10.000',
        'line_total' => '25000.00',
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale1->id,
        'article_id' => $article2->id,
        'quantity' => '5.000',
        'line_total' => '6000.00',
    ]);

    // Venta confirmada en branchB dentro del rango (canal online con web_order_id)
    $sale2 = Sale::factory()->online()->confirmed()->create([
        'point_of_sale_id' => $posB->id,
        'confirmed_at' => '2026-10-12 10:00:00',
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale2->id,
        'article_id' => $article2->id,
        'quantity' => '20.000',
        'line_total' => '24000.00',
    ]);

    // Venta abierta (no confirmada) — debe ser ignorada
    $saleOpen = Sale::factory()->create([
        'point_of_sale_id' => $posA->id,
        'status' => SaleStatus::Open,
        'confirmed_at' => null,
    ]);
    SaleItem::factory()->create([
        'sale_id' => $saleOpen->id,
        'article_id' => $article3->id,
        'quantity' => '100.000',
        'line_total' => '50000.00',
    ]);

    // Venta fuera del rango de fechas — debe ser ignorada
    $saleOld = Sale::factory()->confirmed()->create([
        'point_of_sale_id' => $posA->id,
        'confirmed_at' => '2026-09-25 10:00:00',
    ]);
    SaleItem::factory()->create([
        'sale_id' => $saleOld->id,
        'article_id' => $article3->id,
        'quantity' => '50.000',
        'line_total' => '40000.00',
    ]);

    $action = app(GetDashboardOperationalIndicators::class);

    // Consulta global del mes
    $global = $action->handle([
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-15',
        'branch_id' => null,
        'channel' => null,
    ]);

    expect($global->top_articles)->toHaveCount(2)
        ->and($global->top_articles[0]->article_id)->toBe($article2->id)
        ->and($global->top_articles[0]->quantity)->toBe('25.000')
        ->and($global->top_articles[0]->total_invoiced)->toBe('30000.00')
        ->and($global->top_articles[1]->article_id)->toBe($article1->id)
        ->and($global->top_articles[1]->quantity)->toBe('10.000')
        ->and($global->top_articles[1]->total_invoiced)->toBe('25000.00');

    // Filtro por sucursal A
    $branchFiltered = $action->handle([
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-15',
        'branch_id' => $branchA->id,
        'channel' => null,
    ]);

    expect($branchFiltered->top_articles)->toHaveCount(2)
        ->and($branchFiltered->top_articles[0]->article_id)->toBe($article1->id)
        ->and($branchFiltered->top_articles[0]->quantity)->toBe('10.000')
        ->and($branchFiltered->top_articles[1]->article_id)->toBe($article2->id)
        ->and($branchFiltered->top_articles[1]->quantity)->toBe('5.000');

    // Filtro por canal Online
    $onlineFiltered = $action->handle([
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-15',
        'branch_id' => null,
        'channel' => 'online',
    ]);

    expect($onlineFiltered->top_articles)->toHaveCount(1)
        ->and($onlineFiltered->top_articles[0]->article_id)->toBe($article2->id)
        ->and($onlineFiltered->top_articles[0]->quantity)->toBe('20.000');
});

test('stock alerts report active articles with quantity <= 0 and distinguish stock conflicts', function () {
    $branch = Branch::factory()->create();
    $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
    $inactiveWarehouse = Warehouse::factory()->create(['branch_id' => $branch->id, 'is_active' => false]);

    $activeArticle1 = Article::factory()->create(['status' => ArticleStatus::Active, 'description' => 'Artículo con conflicto']);
    $activeArticle2 = Article::factory()->create(['status' => ArticleStatus::Active, 'description' => 'Artículo sin stock']);
    $activeArticleOk = Article::factory()->create(['status' => ArticleStatus::Active, 'description' => 'Artículo con stock']);
    $inactiveArticle = Article::factory()->create(['status' => ArticleStatus::Inactive, 'description' => 'Artículo inactivo']);

    // Saldo negativo por venta sin existencia (EPIC-06) -> Conflicto
    StockBalance::factory()->create([
        'article_id' => $activeArticle1->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '-3.500',
    ]);

    // Saldo en cero -> Sin stock
    StockBalance::factory()->create([
        'article_id' => $activeArticle2->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '0.000',
    ]);

    // Saldo positivo -> No debe alertar
    StockBalance::factory()->create([
        'article_id' => $activeArticleOk->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '15.000',
    ]);

    // Saldo negativo pero de artículo inactivo -> Debe ignorarse
    StockBalance::factory()->create([
        'article_id' => $inactiveArticle->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '-1.000',
    ]);

    // Saldo negativo en depósito inactivo -> Debe ignorarse
    StockBalance::factory()->create([
        'article_id' => $activeArticle1->id,
        'warehouse_id' => $inactiveWarehouse->id,
        'quantity' => '-5.000',
    ]);

    $action = app(GetDashboardOperationalIndicators::class);
    $result = $action->handle([
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-15',
        'branch_id' => $branch->id,
        'channel' => null,
    ]);

    expect($result->stock_alerts)->toHaveCount(2)
        ->and($result->stock_alerts[0]->article_id)->toBe($activeArticle1->id)
        ->and($result->stock_alerts[0]->quantity)->toBe('-3.500')
        ->and($result->stock_alerts[0]->has_stock_conflict)->toBeTrue()
        ->and($result->stock_alerts[1]->article_id)->toBe($activeArticle2->id)
        ->and($result->stock_alerts[1]->quantity)->toBe('0.000')
        ->and($result->stock_alerts[1]->has_stock_conflict)->toBeFalse();
});

test('supplier ranking orders by net purchases in period and computes current outstanding balance', function () {
    $this->travelTo('2026-10-15 12:00:00');

    $supplierA = Supplier::factory()->create(['business_name' => 'Molinos Río']);
    $supplierB = Supplier::factory()->create(['business_name' => 'Lácteos del Sur']);

    // Supplier A: Factura de $10.000 en el período + Nota de Crédito de $2.000 en el período (neto compras: $8.000)
    SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplierA->id,
        'total_amount' => '10000.00',
        'issue_date' => '2026-10-05',
        'due_date' => '2026-10-25',
    ]);
    SupplierVoucher::factory()->creditNote()->create([
        'supplier_id' => $supplierA->id,
        'total_amount' => '2000.00',
        'issue_date' => '2026-10-08',
        'due_date' => null,
    ]);

    // Supplier B: Factura de $5.000 en el período
    SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplierB->id,
        'total_amount' => '5000.00',
        'issue_date' => '2026-10-10',
        'due_date' => '2026-10-30',
    ]);

    // Comprobante anulado -> debe ignorarse
    SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplierB->id,
        'total_amount' => '50000.00',
        'issue_date' => '2026-10-11',
        'due_date' => '2026-10-30',
        'status' => SupplierVoucherStatus::Cancelled,
    ]);

    // Comprobante fuera de fecha -> no suma a compras del período pero sí cuenta en el saldo pendiente
    SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplierB->id,
        'total_amount' => '3000.00',
        'issue_date' => '2026-08-01',
        'due_date' => '2026-08-30',
    ]);

    $action = app(GetDashboardOperationalIndicators::class);
    $result = $action->handle([
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-15',
        'branch_id' => null,
        'channel' => null,
    ]);

    expect($result->supplier_ranking)->toHaveCount(2)
        // La NC libre reduce las compras netas, pero no el saldo de la factura.
        ->and($result->supplier_ranking[0]->supplier_id)->toBe($supplierA->id)
        ->and($result->supplier_ranking[0]->purchases_total)->toBe('8000.00')
        ->and($result->supplier_ranking[0]->outstanding_balance)->toBe('10000.00')
        // Segundo puesto: Supplier B ($5.000 compras en el período, deuda acumulada total = $5.000 + $3.000 = $8.000)
        ->and($result->supplier_ranking[1]->supplier_id)->toBe($supplierB->id)
        ->and($result->supplier_ranking[1]->purchases_total)->toBe('5000.00')
        ->and($result->supplier_ranking[1]->outstanding_balance)->toBe('8000.00');
});

test('supplier ranking preserves negative net purchases from credit notes for previous purchases', function () {
    $supplier = Supplier::factory()->create();
    SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '10000.00',
        'issue_date' => '2026-09-01',
        'due_date' => null,
    ]);
    SupplierVoucher::factory()->creditNote()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '2000.00',
        'issue_date' => '2026-10-08',
    ]);

    $result = app(GetDashboardOperationalIndicators::class)->handle([
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-15',
        'branch_id' => null,
        'channel' => null,
    ]);

    expect($result->supplier_ranking)->toHaveCount(1)
        ->and($result->supplier_ranking[0]->purchases_total)->toBe('-2000.00')
        ->and($result->supplier_ranking[0]->outstanding_balance)->toBe('10000.00');
});

test('pending web orders count correctly identifies orders ready for delivery', function () {
    // 2 pagados creados con número explícito
    WebOrder::factory()->paid()->create(['number' => 101]);
    WebOrder::factory()->paid()->create(['number' => 102]);

    // 1 en preparación
    WebOrder::factory()->inPreparation()->create(['number' => 103]);

    // 1 ya entregado (no debe contar)
    WebOrder::factory()->delivered()->create(['number' => 104]);

    // 1 pendiente de pago (no debe contar)
    WebOrder::factory()->create(['status' => WebOrderStatus::Pending, 'number' => 105]);

    $action = app(GetDashboardOperationalIndicators::class);

    // Consulta global: 2 pagados + 1 en preparación = 3
    $global = $action->handle([
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-15',
        'branch_id' => null,
        'channel' => null,
    ]);
    expect($global->pending_web_orders_count)->toBe(3);

    // Si se filtra por canal 'mostrador', debe dar 0
    $counter = $action->handle([
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-15',
        'branch_id' => null,
        'channel' => 'mostrador',
    ]);
    expect($counter->pending_web_orders_count)->toBe(0);

    // Si se filtra por canal 'online', debe dar 3
    $online = $action->handle([
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-15',
        'branch_id' => null,
        'channel' => 'online',
    ]);
    expect($online->pending_web_orders_count)->toBe(3);
});

test('supplier ranking derives debt from applied credit notes and active payments across periods', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '10000.00',
        'issue_date' => '2026-09-01',
        'due_date' => null,
    ]);
    $creditNote = SupplierVoucher::factory()->creditNote()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => '2000.00',
        'issue_date' => '2026-10-08',
    ]);
    app(AssociateCreditNoteToInvoice::class)->handle($creditNote, $invoice->id, '2000.00', User::factory()->create()->id);

    PaymentOrderItem::factory()->forInvoice($invoice, '3000.00')->create([
        'payment_order_id' => PaymentOrder::factory()->create(['supplier_id' => $supplier->id, 'total_amount' => '3000.00'])->id,
    ]);
    PaymentOrderItem::factory()->forInvoice($invoice, '1000.00')->create([
        'payment_order_id' => PaymentOrder::factory()->cancelled()->create(['supplier_id' => $supplier->id, 'total_amount' => '1000.00'])->id,
    ]);

    $result = app(GetDashboardOperationalIndicators::class)->handle([
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-15',
        'branch_id' => null,
        'channel' => null,
    ]);

    expect($result->supplier_ranking)->toHaveCount(1)
        ->and($result->supplier_ranking[0]->purchases_total)->toBe('-2000.00')
        ->and($result->supplier_ranking[0]->outstanding_balance)->toBe('5000.00');
});

test('supplier ranking filters purchases by branch and inclusive dates independently of channel', function (?string $channel) {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $warehouseA = Warehouse::factory()->create(['branch_id' => $branchA->id]);
    $warehouseB = Warehouse::factory()->create(['branch_id' => $branchB->id]);
    $supplier = Supplier::factory()->create();

    foreach ([
        [$warehouseA->id, '2026-10-01', '100.00'],
        [null, '2026-10-15', '200.00'],
        [$warehouseB->id, '2026-10-10', '400.00'],
        [$warehouseA->id, '2026-09-30', '800.00'],
        [$warehouseA->id, '2026-10-16', '1600.00'],
    ] as [$warehouseId, $date, $amount]) {
        SupplierVoucher::factory()->invoice()->create([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouseId,
            'issue_date' => $date,
            'due_date' => null,
            'total_amount' => $amount,
        ]);
    }

    $result = app(GetDashboardOperationalIndicators::class)->handle([
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-15',
        'branch_id' => $branchA->id,
        'channel' => $channel,
    ]);

    expect($result->supplier_ranking)->toHaveCount(1)
        ->and($result->supplier_ranking[0]->purchases_total)->toBe('300.00')
        ->and($result->supplier_ranking[0]->outstanding_balance)->toBe('3100.00');
})->with([null, 'mostrador', 'online']);

test('supplier ranking orders positive zero and negative net purchases', function () {
    $positive = Supplier::factory()->create();
    $zero = Supplier::factory()->create();
    $negative = Supplier::factory()->create();

    foreach ([$negative, $zero, $positive] as $supplier) {
        SupplierVoucher::factory()->invoice()->create([
            'supplier_id' => $supplier->id,
            'total_amount' => '100.00',
            'issue_date' => '2026-10-01',
            'due_date' => null,
        ]);
    }
    foreach ([[$zero, '100.00'], [$negative, '200.00']] as [$supplier, $amount]) {
        SupplierVoucher::factory()->creditNote()->create([
            'supplier_id' => $supplier->id,
            'total_amount' => $amount,
            'issue_date' => '2026-10-08',
        ]);
    }
    SupplierVoucher::factory()->creditNote()->create([
        'supplier_id' => $positive->id,
        'total_amount' => '1000.00',
        'issue_date' => '2026-10-08',
        'status' => SupplierVoucherStatus::Cancelled,
    ]);

    $result = app(GetDashboardOperationalIndicators::class)->handle([
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-15',
        'branch_id' => null,
        'channel' => null,
    ]);

    expect(array_column($result->supplier_ranking, 'supplier_id'))->toBe([$positive->id, $zero->id, $negative->id])
        ->and(array_column($result->supplier_ranking, 'purchases_total'))->toBe(['100.00', '0.00', '-100.00']);
});

test('supplier ranking limits results to ten after aggregating every voucher batch', function () {
    $suppliers = Supplier::factory()->count(11)->create();

    foreach ($suppliers as $index => $supplier) {
        SupplierVoucher::factory()->invoice()->create([
            'supplier_id' => $supplier->id,
            'total_amount' => (string) (($index + 1) * 100),
            'issue_date' => '2026-10-01',
            'due_date' => null,
        ]);
    }

    $boostedSupplier = $suppliers->first();
    SupplierVoucher::factory()->invoice()->count(501)->create([
        'supplier_id' => $boostedSupplier->id,
        'total_amount' => '2.01',
        'issue_date' => '2026-10-15',
        'due_date' => null,
    ]);
    SupplierVoucher::factory()->debitNote()->create([
        'supplier_id' => $boostedSupplier->id,
        'total_amount' => '0.05',
        'issue_date' => '2026-10-15',
        'due_date' => null,
    ]);

    $result = app(GetDashboardOperationalIndicators::class)->handle([
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-15',
        'branch_id' => null,
        'channel' => null,
    ]);

    $expectedIds = [$boostedSupplier->id, ...$suppliers->slice(2)->reverse()->modelKeys()];
    expect($result->supplier_ranking)->toHaveCount(10)
        ->and(array_column($result->supplier_ranking, 'supplier_id'))->toBe($expectedIds)
        ->and($result->supplier_ranking[0]->purchases_total)->toBe('1107.06')
        ->and($result->supplier_ranking[0]->outstanding_balance)->toBe('1107.06');
});

test('stock alerts respect branch and channel independently of dates', function (?string $channel, bool $filterBranch) {
    $branches = Branch::factory()->count(2)->create();
    $article = Article::factory()->create(['status' => ArticleStatus::Active]);
    $expectedWarehouseIds = [];

    foreach ($branches as $branchIndex => $branch) {
        foreach ([false, true] as $online) {
            $warehouse = Warehouse::factory()->create([
                'branch_id' => $branch->id,
                'is_online_channel' => $online,
                'is_active' => true,
            ]);
            StockBalance::factory()->create([
                'article_id' => $article->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => '0.000',
            ]);
            if ((! $filterBranch || $branchIndex === 0) && ($channel === null || $online === ($channel === 'online'))) {
                $expectedWarehouseIds[] = $warehouse->id;
            }
        }
    }

    foreach ([['2026-10-01', '2026-10-15'], ['2026-01-01', '2026-01-02']] as [$from, $to]) {
        $result = app(GetDashboardOperationalIndicators::class)->handle([
            'date_from' => $from,
            'date_to' => $to,
            'branch_id' => $filterBranch ? $branches->first()->id : null,
            'channel' => $channel,
        ]);

        expect(array_column($result->stock_alerts, 'warehouse_id'))->toEqualCanonicalizing($expectedWarehouseIds);
    }
})->with([null, 'mostrador', 'online'])->with([false, true]);

test('pending orders use pickup branch before sale warehouse and ignore date filters', function (?string $channel) {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $warehouseA = Warehouse::factory()->create(['branch_id' => $branchA->id]);
    $warehouseB = Warehouse::factory()->create(['branch_id' => $branchB->id]);
    $posA = PointOfSale::factory()->create(['warehouse_id' => $warehouseA->id]);
    $posB = PointOfSale::factory()->create(['warehouse_id' => $warehouseB->id]);

    $pickupA = WebOrder::factory()->paid()->create(['pickup_branch_id' => $branchA->id]);
    Sale::factory()->online()->confirmed()->create(['web_order_id' => $pickupA->id, 'point_of_sale_id' => $posB->id]);
    $pickupB = WebOrder::factory()->inPreparation()->create(['pickup_branch_id' => $branchB->id]);
    Sale::factory()->online()->confirmed()->create(['web_order_id' => $pickupB->id, 'point_of_sale_id' => $posA->id]);

    $shippingA = WebOrder::factory()->shipping()->inPreparation()->create();
    Sale::factory()->online()->confirmed()->create(['web_order_id' => $shippingA->id, 'point_of_sale_id' => $posA->id]);
    $shippingB = WebOrder::factory()->shipping()->paid()->create();
    Sale::factory()->online()->confirmed()->create(['web_order_id' => $shippingB->id, 'point_of_sale_id' => $posB->id]);

    WebOrder::factory()->shipping()->paid()->create();
    WebOrder::factory()->delivered()->create(['pickup_branch_id' => $branchA->id]);
    WebOrder::factory()->create(['pickup_branch_id' => $branchA->id]);

    foreach ([['2026-10-01', '2026-10-15'], ['2026-01-01', '2026-01-02']] as [$from, $to]) {
        foreach ([[$branchA->id, 2], [$branchB->id, 2], [null, 5]] as [$branchId, $count]) {
            $result = app(GetDashboardOperationalIndicators::class)->handle([
                'date_from' => $from,
                'date_to' => $to,
                'branch_id' => $branchId,
                'channel' => $channel,
            ]);

            expect($result->pending_web_orders_count)->toBe($channel === 'mostrador' ? 0 : $count);
        }
    }
})->with([null, 'mostrador', 'online']);
