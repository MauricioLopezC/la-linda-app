<?php

use App\Enums\Sales\SaleChannel;
use App\Enums\Security\UserRole;
use App\Models\Catalog\Article;
use App\Models\Ecommerce\WebOrder;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\Sales\CashMovement;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login when exporting the dashboard', function () {
    $this->get(route('dashboard.export'))->assertRedirect(route('login'));
    $this->get(route('dashboard.export.csv'))->assertRedirect(route('login'));
    $this->get(route('dashboard.export.excel'))->assertRedirect(route('login'));
});

test('staff without view-dashboard permission cannot export the dashboard', function () {
    $cajero = User::factory()->cajero()->create();

    $this->actingAs($cajero)->get(route('dashboard.export'))->assertForbidden();
    $this->actingAs($cajero)->get(route('dashboard.export.csv'))->assertForbidden();
    $this->actingAs($cajero)->get(route('dashboard.export.excel'))->assertForbidden();
});

test('gerente and administrador can export the dashboard to CSV and Excel', function (UserRole $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)
        ->get(route('dashboard.export.csv'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $this->actingAs($user)
        ->get(route('dashboard.export.excel'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
})->with([
    'gerente' => [UserRole::Gerente],
    'administrador' => [UserRole::Administrador],
]);

test('endpoint dashboard.export dispatches to excel by default and csv with format query', function () {
    $user = User::factory()->role(UserRole::Gerente)->create();

    $this->actingAs($user)
        ->get(route('dashboard.export'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $this->actingAs($user)
        ->get(route('dashboard.export', ['format' => 'csv']))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $this->actingAs($user)
        ->get(route('dashboard.export', ['format' => 'xlsx']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('dashboard export rejects invalid filters', function (array $query, string $field) {
    $this->travelTo('2026-10-15 10:00:00');
    $user = User::factory()->role(UserRole::Gerente)->create();

    $this->actingAs($user)
        ->get(route('dashboard.export', $query))
        ->assertSessionHasErrors($field);
})->with([
    'date_to before date_from' => [['date_from' => '2026-10-10', 'date_to' => '2026-10-01'], 'date_to'],
    'unknown channel' => [['channel' => 'mayorista'], 'channel'],
    'missing branch' => [['branch_id' => 999999], 'branch_id'],
    'invalid format' => [['format' => 'pdf'], 'format'],
]);

test('csv export contains UTF-8 BOM, semicolon delimiters and all 4 dashboard block sections', function () {
    $this->travelTo('2026-10-15 12:00:00');
    $user = User::factory()->role(UserRole::Gerente)->create();

    $response = $this->actingAs($user)->get(route('dashboard.export.csv'));
    $response->assertOk();

    $content = $response->streamedContent();

    // UTF-8 BOM check
    expect(str_starts_with($content, chr(0xEF).chr(0xBB).chr(0xBF)))->toBeTrue();

    // Section headers
    expect($content)
        ->toContain('TABLERO GERENCIAL - SUPERMERCADOS LA LINDA')
        ->toContain('1. RESUMEN DE INGRESOS')
        ->toContain('2. RESUMEN DE EGRESOS')
        ->toContain('3. RESULTADO Y EVOLUCIÓN TEMPORAL')
        ->toContain('4. INDICADORES OPERATIVOS COMPLEMENTARIOS')
        ->toContain('Desglose de Ingresos por Canal')
        ->toContain('Desglose de Ingresos por Medio de Pago')
        ->toContain('Desglose de Pagos a Proveedores por Medio de Pago')
        ->toContain('Serie Temporal de Evolución')
        ->toContain('Ranking de Artículos Más Vendidos')
        ->toContain('Alertas de Stock Crítico y Conflictos')
        ->toContain('Ranking de Compras por Proveedor');
});

test('verification: filtering by branch and mostrador channel reflects matching figures in screen and export', function () {
    $this->travelTo('2026-10-15 12:00:00');

    $branchA = Branch::factory()->create(['name' => 'Sucursal Centro']);
    $warehouseA = Warehouse::factory()->create(['branch_id' => $branchA->id, 'name' => 'Depósito Centro']);
    $posA = PointOfSale::factory()->create(['warehouse_id' => $warehouseA->id]);

    $branchB = Branch::factory()->create(['name' => 'Sucursal Norte']);
    $warehouseB = Warehouse::factory()->create(['branch_id' => $branchB->id, 'name' => 'Depósito Norte']);
    $posB = PointOfSale::factory()->create(['warehouse_id' => $warehouseB->id]);

    $cash = PaymentMethod::factory()->cash()->create(['name' => 'Efectivo Mostrador']);
    $article = Article::factory()->create(['description' => 'Yerba Mate Premium 1kg']);

    // Venta en Sucursal A por mostrador: total 1210.00 (neto 1000.00)
    $saleA = Sale::factory()->confirmed()->create([
        'point_of_sale_id' => $posA->id,
        'channel' => SaleChannel::Mostrador,
        'total_amount' => '1210.00',
        'confirmed_at' => '2026-10-15 10:00:00',
    ]);
    Invoice::factory()->create([
        'sale_id' => $saleA->id,
        'net_amount' => '1000.00',
        'vat_amount' => '210.00',
        'total_amount' => '1210.00',
    ]);
    SaleItem::factory()->create([
        'sale_id' => $saleA->id,
        'article_id' => $article->id,
        'quantity' => '5.000',
        'line_total' => '1210.00',
    ]);
    $saleA->user_id = User::factory()->create()->id;
    $saleA->save();

    CashMovement::factory()->forSale($saleA)->create([
        'payment_method_id' => $cash->id,
        'amount' => '1210.00',
        'user_id' => $saleA->user_id,
    ]);

    // Venta en Sucursal B por online (no debe figurar al filtrar por Sucursal A)
    $webOrder = WebOrder::factory()->paid()->create();
    $saleB = Sale::factory()->confirmed()->create([
        'point_of_sale_id' => $posB->id,
        'channel' => SaleChannel::Online,
        'web_order_id' => $webOrder->id,
        'total_amount' => '5000.00',
        'confirmed_at' => '2026-10-15 11:00:00',
    ]);
    Invoice::factory()->create([
        'sale_id' => $saleB->id,
        'net_amount' => '4132.23',
        'vat_amount' => '867.77',
        'total_amount' => '5000.00',
    ]);

    $filterParams = [
        'branch_id' => $branchA->id,
        'channel' => 'mostrador',
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-15',
    ];

    $user = User::factory()->role(UserRole::Gerente)->create();

    // 1. Verificación en pantalla del dashboard (Inertia props)
    $this->actingAs($user)
        ->get(route('dashboard', $filterParams))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('filters.branch_id', $branchA->id)
            ->where('filters.channel', 'mostrador')
            ->where('revenues.total_invoiced', '1210.00')
            ->where('revenues.net_revenue', '1000.00')
            ->where('revenues.confirmed_sales_count', 1)
            ->where('operational.top_articles.0.description', 'Yerba Mate Premium 1kg')
            ->where('operational.top_articles.0.quantity', '5.000')
        );

    // 2. Verificación en exportación CSV
    $csvResponse = $this->actingAs($user)
        ->get(route('dashboard.export.csv', $filterParams))
        ->assertOk();

    $csvContent = $csvResponse->streamedContent();

    expect($csvContent)
        ->toContain('Sucursal Centro')
        ->toContain('Mostrador')
        ->toContain('"Total Facturado";1210.00')
        ->toContain('"Ingresos Netos (sin IVA)";1000.00')
        ->toContain('Yerba Mate Premium 1kg')
        ->toContain('Efectivo Mostrador')
        ->not->toContain('5000.00')
        ->not->toContain('Sucursal Norte');

    // 3. Verificación en exportación Excel
    $excelResponse = $this->actingAs($user)
        ->get(route('dashboard.export.excel', $filterParams))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('export includes clarifying notes for online channel and branch filter', function () {
    $this->travelTo('2026-10-15 12:00:00');
    $branch = Branch::factory()->create(['name' => 'Sucursal Este']);
    $user = User::factory()->role(UserRole::Gerente)->create();

    $response = $this->actingAs($user)->get(route('dashboard.export.csv', [
        'channel' => 'online',
        'branch_id' => $branch->id,
    ]));

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)
        ->toContain('Aclaración de canal online')
        ->toContain('Las compras y pagos a proveedores se consolidan como globales de la empresa')
        ->toContain('Aclaración de sucursal')
        ->toContain('El desembolso total combina pagos globales a proveedores y egresos de caja');
});
