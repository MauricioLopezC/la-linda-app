<?php

use App\Actions\Reporting\GetDashboardEvolution;
use App\Actions\Reporting\GetDashboardExpenses;
use App\Actions\Reporting\GetDashboardOperationalIndicators;
use App\Actions\Reporting\GetDashboardRevenues;
use App\Data\Reporting\DashboardBreakdownItemData;
use App\Data\Reporting\DashboardEvolutionData;
use App\Data\Reporting\DashboardEvolutionPointData;
use App\Data\Reporting\DashboardExpensesData;
use App\Data\Reporting\DashboardOperationalData;
use App\Data\Reporting\DashboardRevenuesData;
use App\Data\Reporting\DashboardStockAlertData;
use App\Data\Reporting\DashboardSupplierRankingData;
use App\Data\Reporting\DashboardTopArticleData;
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
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

/** @return list<list<string|int|float|null>> */
function dashboardExportRows(TestResponse $response): array
{
    if ($response->baseResponse instanceof BinaryFileResponse) {
        $path = $response->baseResponse->getFile()->getPathname();
        $reader = new Reader;
        $reader->open($path);

        try {
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $values = $row->toArray();
                    while ($values !== [] && in_array(end($values), [null, ''], true)) {
                        array_pop($values);
                    }
                    $rows[] = $values;
                }
            }

            return $rows;
        } finally {
            $reader->close();
            unlink($path);
        }
    }

    $handle = fopen('php://memory', 'r+');
    fwrite($handle, substr($response->streamedContent(), 3));
    rewind($handle);

    try {
        $rows = [];
        while (($row = fgetcsv($handle, separator: ';', escape: '')) !== false) {
            $rows[] = $row;
        }

        return $rows;
    } finally {
        fclose($handle);
    }
}

function fakeDashboardExportData(string $text = '00123', ?float $variation = 25.5): void
{
    $breakdown = new DashboardBreakdownItemData('test', $text, '1210.15', 1);
    mock(GetDashboardRevenues::class)->shouldReceive('handle')->andReturn(new DashboardRevenuesData(
        '1210.15', '1000.12', '210.03', 1, '1210.15', $variation,
        [$breakdown], [$breakdown], [$breakdown],
    ));
    mock(GetDashboardExpenses::class)->shouldReceive('handle')->andReturn(new DashboardExpensesData(
        '2000.32', '100.10', '20.25', '120.35', [$breakdown],
    ));
    mock(GetDashboardEvolution::class)->shouldReceive('handle')->andReturn(new DashboardEvolutionData(
        '-1000.20', -100.0, -50.0,
        [new DashboardEvolutionPointData('2026-10-15', '1000.12', '2000.32', '-1000.20')],
    ));
    mock(GetDashboardOperationalIndicators::class)->shouldReceive('handle')->andReturn(new DashboardOperationalData(
        [new DashboardTopArticleData(1, $text, '1.125', '1210.15')],
        [new DashboardStockAlertData(1, $text, 1, $text, '-1.375', true)],
        [new DashboardSupplierRankingData(1, $text, '987654321.09', '-100.25')],
        3,
    ));
}

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
            ->where('revenues.vat_total', '210.00')
            ->where('revenues.average_ticket', '1210.00')
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
        ->toContain('"IVA Total";210.00')
        ->toContain('"Ticket Promedio";1210.00')
        ->toContain('"Sucursal Centro";1;1210.00')
        ->toContain('Yerba Mate Premium 1kg')
        ->toContain('Efectivo Mostrador')
        ->not->toContain('5000.00')
        ->not->toContain('Sucursal Norte');

    // 3. Verificación en exportación Excel
    $excelResponse = $this->actingAs($user)
        ->get(route('dashboard.export.excel', $filterParams))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $excelRows = dashboardExportRows($excelResponse);
    expect($excelRows)
        ->toContain(['Total Facturado', 1210])
        ->toContain(['Ingresos Netos (sin IVA)', 1000])
        ->toContain(['IVA Total', 210])
        ->toContain(['Ticket Promedio', 1210])
        ->toContain(['Sucursal Centro', 1, 1210])
        ->toContain([1, 'Yerba Mate Premium 1kg', 5, 1210]);
    expect(array_merge(...$excelRows))->not->toContain('Sucursal Norte', 5000);
});

test('dashboard exports include all revenue KPIs and consolidated branch breakdown', function (string $format) {
    $this->travelTo('2026-10-15 12:00:00');
    $this->actingAs(User::factory()->gerente()->create());
    $pos = PointOfSale::factory()->create();
    $branch = $pos->warehouse->branch;

    foreach (['2026-09-30 10:00:00' => '605.00', '2026-10-15 10:00:00' => '1210.00'] as $date => $amount) {
        $sale = Sale::factory()->confirmed()->create([
            'point_of_sale_id' => $pos->id,
            'total_amount' => $amount,
            'confirmed_at' => $date,
        ]);
        Invoice::factory()->create([
            'sale_id' => $sale->id,
            'net_amount' => $amount === '605.00' ? '500.00' : '1000.00',
            'vat_amount' => $amount === '605.00' ? '105.00' : '210.00',
            'total_amount' => $amount,
        ]);
    }

    $filters = ['date_from' => '2026-10-01', 'date_to' => '2026-10-15'];
    $this->get(route('dashboard', $filters))->assertInertia(fn ($page) => $page
        ->where('revenues.vat_total', '210.00')
        ->where('revenues.average_ticket', '1210.00')
        ->where('revenues.total_invoiced_variation_pct', 100)
        ->where('revenues.by_branch.0.label', $branch->name)
        ->where('revenues.by_branch.0.amount', '1210.00')
    );

    $rows = dashboardExportRows($this->get(route('dashboard.export.'.$format, $filters))->assertOk());
    expect($rows)
        ->toContain(['IVA Total', $format === 'csv' ? '210.00' : 210])
        ->toContain(['Ticket Promedio', $format === 'csv' ? '1210.00' : 1210])
        ->toContain(['Variación Total Facturado (%)', $format === 'csv' ? '+100%' : 1])
        ->toContain(['Desglose de Ingresos por Sucursal'])
        ->toContain([$branch->name, $format === 'csv' ? '1' : 1, $format === 'csv' ? '1210.00' : 1210]);
})->with(['csv', 'excel']);

test('dashboard export treats every external label as literal text', function (string $text, bool $requiresEscaping) {
    fakeDashboardExportData($text);
    $branch = Branch::factory()->create(['name' => $text]);
    $this->actingAs(User::factory()->gerente()->create());

    $filters = ['branch_id' => $branch->id];
    $response = $this->get(route('dashboard.export.excel', $filters))->assertOk();
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    expect($xml)->not->toContain('<f>');

    $excelRows = dashboardExportRows($response);
    $excelText = str_replace(["\r\n", "\r"], "\n", $text);
    expect($excelRows)
        ->toContain(['Sucursal', $excelText])
        ->toContain([$excelText, 1, 1210.15])
        ->toContain([1, $excelText, 1.125, 1210.15])
        ->toContain([$excelText, $excelText, -1.375, 'Conflicto por venta sin stock'])
        ->toContain([1, $excelText, 987654321.09, -100.25]);

    $csvRows = dashboardExportRows($this->get(route('dashboard.export.csv', $filters))->assertOk());
    $safeText = $requiresEscaping ? "'".$text : $text;
    expect($csvRows)
        ->toContain(['Sucursal', $safeText])
        ->toContain([$safeText, '1', '1210.15'])
        ->toContain(['1', $safeText, '1.125', '1210.15'])
        ->toContain([$safeText, $safeText, '-1.375', 'Conflicto por venta sin stock'])
        ->toContain(['1', $safeText, '987654321.09', '-100.25'])
        ->toContain(['Resultado Neto del Período', '-1000.20'])
        ->toContain(['Variación Período Anterior (%)', '-50%']);
})->with([
    'formula' => ['=1+1', true],
    'plus' => ['+1+1', true],
    'minus' => ['-1+1', true],
    'at' => ['@SUM(1;2)', true],
    'leading spaces' => ['  =1+1', true],
    'tab' => ["\tProducto", true],
    'carriage return' => ["\rProducto", true],
    'line feed' => ["\nProducto", true],
    'full width formula' => ['＝1+1', true],
    'numeric name' => ['00123', false],
    'quotes and separators' => ['Artículo "Especial"; =1+1', false],
    'backslash before quote' => ['Artículo \\";=1+1', false],
]);

test('xlsx exports numeric amounts quantities and percentages with their formats', function () {
    fakeDashboardExportData();
    $this->actingAs(User::factory()->gerente()->create());
    $response = $this->get(route('dashboard.export.excel'))->assertOk();
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $worksheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
    $styles = simplexml_load_string($zip->getFromName('xl/styles.xml'));
    $zip->close();
    $worksheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    foreach (['1210.15' => '0.00', '1.125' => '0.000', '-1.375' => '0.000', '0.255' => '+0.0%;-0.0%;0.0%'] as $value => $format) {
        $cells = $worksheet->xpath('//s:c[s:v="'.$value.'"]');
        expect($cells)->not->toBeEmpty();
        foreach ($cells as $cell) {
            $styleIndex = (int) $cell['s'];
            $formatId = (int) $styles->cellXfs->xf[$styleIndex]['numFmtId'];
            $numberFormats = $styles->numFmts->numFmt;
            $actualFormat = $formatId === 2 ? '0.00' : null;
            foreach ($numberFormats as $numberFormat) {
                if ((int) $numberFormat['numFmtId'] === $formatId) {
                    $actualFormat = (string) $numberFormat['formatCode'];
                }
            }
            expect($actualFormat)->toBe($format);
        }
    }

    expect(dashboardExportRows($response))
        ->toContain(['Comprobantes Recibidos (Devengado Proveedores)', 2000.32])
        ->toContain(['Pagado a Proveedores (Órdenes Emitidas)', 100.10])
        ->toContain(['Egresos Operativos de Caja', 20.25])
        ->toContain(['Desembolso Total Efectivo', 120.35])
        ->toContain(['2026-10-15', 1000.12, 2000.32, -1000.20])
        ->toContain(['Margen Neto (%)', -1])
        ->toContain(['Variación Período Anterior (%)', -0.5])
        ->toContain(['Pedidos Web Pendientes de Preparación / Entrega', 3]);
});

test('dashboard exports preserve zero negative and unavailable variations', function (?float $variation, string $format, string|float|int $expected) {
    fakeDashboardExportData(variation: $variation);
    $this->actingAs(User::factory()->gerente()->create());
    expect(dashboardExportRows($this->get(route('dashboard.export.'.$format))->assertOk()))
        ->toContain(['Variación Total Facturado (%)', $expected]);
})->with([
    'csv unavailable' => [null, 'csv', 'N/D'],
    'xlsx unavailable' => [null, 'excel', 'N/D'],
    'csv zero' => [0.0, 'csv', '0%'],
    'xlsx zero' => [0.0, 'excel', 0],
    'csv negative' => [-25.5, 'csv', '-25.5%'],
    'xlsx negative' => [-25.5, 'excel', -0.255],
]);

test('dashboard exports include empty branch breakdown and zero KPIs', function (string $format) {
    $this->actingAs(User::factory()->gerente()->create());
    $rows = dashboardExportRows($this->get(route('dashboard.export.'.$format))->assertOk());
    expect($rows)
        ->toContain(['Desglose de Ingresos por Sucursal'])
        ->toContain(['IVA Total', $format === 'csv' ? '0.00' : 0])
        ->toContain(['Ticket Promedio', $format === 'csv' ? '0.00' : 0])
        ->toContain(['Variación Total Facturado (%)', 'N/D'])
        ->toContain(['Sin datos en el período', $format === 'csv' ? '0' : 0, $format === 'csv' ? '0.00' : 0]);
})->with(['csv', 'excel']);

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
