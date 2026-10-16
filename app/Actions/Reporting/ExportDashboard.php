<?php

namespace App\Actions\Reporting;

use App\Enums\Sales\SaleChannel;
use App\Models\Organization\Branch;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportDashboard
{
    public function __construct(
        public GetDashboardRevenues $getRevenues,
        public GetDashboardExpenses $getExpenses,
        public GetDashboardEvolution $getEvolution,
        public GetDashboardOperationalIndicators $getOperational,
    ) {}

    /**
     * Export the management dashboard summary to CSV.
     *
     * @param  array{date_from: string, date_to: string, branch_id: ?int, channel: ?string}  $filters
     */
    public function toCsv(array $filters): StreamedResponse
    {
        $rows = $this->buildRows($filters);
        $filename = 'tablero_gerencial_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Export the management dashboard summary to Excel (XLSX).
     *
     * @param  array{date_from: string, date_to: string, branch_id: ?int, channel: ?string}  $filters
     */
    public function toExcel(array $filters): BinaryFileResponse
    {
        $rows = $this->buildRows($filters);
        $filename = 'tablero_gerencial_'.now()->format('Ymd_His').'.xlsx';
        $tempPath = tempnam(sys_get_temp_dir(), 'export_') ?: sys_get_temp_dir().'/export_'.uniqid().'.xlsx';

        $writer = new Writer;
        $writer->openToFile($tempPath);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Build the structured rows consolidating the 4 dashboard blocks.
     *
     * @param  array{date_from: string, date_to: string, branch_id: ?int, channel: ?string}  $filters
     * @return list<list<string|int|float>>
     */
    public function buildRows(array $filters): array
    {
        $revenues = $this->getRevenues->handle($filters);
        $expenses = $this->getExpenses->handle($filters);
        $evolution = $this->getEvolution->handle($filters, $revenues, $expenses);
        $operational = $this->getOperational->handle($filters);

        $branchName = 'Todas las sucursales';
        if ($filters['branch_id'] !== null) {
            $branch = Branch::query()->find($filters['branch_id']);
            $branchName = $branch ? $branch->name : ('Sucursal #'.$filters['branch_id']);
        }

        $channelLabel = 'Todos los canales';
        if ($filters['channel'] !== null) {
            $channelEnum = SaleChannel::tryFrom($filters['channel']);
            $channelLabel = $channelEnum ? $channelEnum->label() : ucfirst($filters['channel']);
        }

        /** @var list<list<string|int|float>> $rows */
        $rows = [];

        // 1. Metadata and Header
        $rows[] = ['TABLERO GERENCIAL - SUPERMERCADOS LA LINDA'];
        $rows[] = ['Período', "{$filters['date_from']} al {$filters['date_to']}"];
        $rows[] = ['Sucursal', $branchName];
        $rows[] = ['Canal', $channelLabel];
        $rows[] = ['Fecha y hora de emisión', now()->format('d/m/Y H:i:s')];

        if ($filters['channel'] === 'online') {
            $rows[] = ['Aclaración de canal online', 'Las compras y pagos a proveedores se consolidan como globales de la empresa. La sucursal seleccionada filtra los egresos de caja.'];
        }

        if ($filters['branch_id'] !== null) {
            $rows[] = ['Aclaración de sucursal', 'El desembolso total combina pagos globales a proveedores y egresos de caja de la sucursal seleccionada.'];
        }

        $rows[] = [];

        // 2. Resumen de Ingresos (EPIC-18)
        $rows[] = ['1. RESUMEN DE INGRESOS'];
        $rows[] = ['Total Facturado', $revenues->total_invoiced];
        $rows[] = ['Ingresos Netos (sin IVA)', $revenues->net_revenue];
        $rows[] = ['Ventas Confirmadas (cantidad)', $revenues->confirmed_sales_count];
        $rows[] = [];

        $rows[] = ['Desglose de Ingresos por Canal'];
        $rows[] = ['Canal', 'Cantidad de Ventas', 'Monto Facturado'];
        if (empty($revenues->by_channel)) {
            $rows[] = ['Sin datos en el período', 0, '0.00'];
        } else {
            foreach ($revenues->by_channel as $channelItem) {
                $rows[] = [$channelItem->label, $channelItem->count, $channelItem->amount];
            }
        }
        $rows[] = [];

        $rows[] = ['Desglose de Ingresos por Medio de Pago'];
        $rows[] = ['Medio de Pago', 'Cantidad de Operaciones', 'Monto Cobrado'];
        if (empty($revenues->by_payment_method)) {
            $rows[] = ['Sin datos en el período', 0, '0.00'];
        } else {
            foreach ($revenues->by_payment_method as $methodItem) {
                $rows[] = [$methodItem->label, $methodItem->count, $methodItem->amount];
            }
        }
        $rows[] = [];

        // 3. Resumen de Egresos (EPIC-19)
        $rows[] = ['2. RESUMEN DE EGRESOS'];
        $rows[] = ['Comprobantes Recibidos (Devengado Proveedores)', $expenses->accrued_total];
        $rows[] = ['Pagado a Proveedores (Órdenes Emitidas)', $expenses->paid_to_suppliers_total];
        $rows[] = ['Egresos Operativos de Caja', $expenses->cash_expenses_total];
        $rows[] = ['Desembolso Total Efectivo', $expenses->disbursed_total];
        $rows[] = [];

        $rows[] = ['Desglose de Pagos a Proveedores por Medio de Pago'];
        $rows[] = ['Medio de Pago', 'Cantidad de Pagos', 'Monto Pagado'];
        if (empty($expenses->by_payment_method)) {
            $rows[] = ['Sin pagos a proveedores en el período', 0, '0.00'];
        } else {
            foreach ($expenses->by_payment_method as $methodItem) {
                $rows[] = [$methodItem->label, $methodItem->count, $methodItem->amount];
            }
        }
        $rows[] = [];

        // 4. Resultado y Evolución Temporal (EPIC-20)
        $rows[] = ['3. RESULTADO Y EVOLUCIÓN TEMPORAL'];
        $rows[] = ['Resultado Neto del Período', $evolution->net_result];
        $rows[] = ['Margen Neto (%)', $evolution->net_margin_pct !== null ? "{$evolution->net_margin_pct}%" : 'N/D'];
        $rows[] = ['Variación Período Anterior (%)', $evolution->net_result_variation_pct !== null ? (($evolution->net_result_variation_pct > 0 ? '+' : '')."{$evolution->net_result_variation_pct}%") : 'N/D'];
        $rows[] = [];

        $rows[] = ['Serie Temporal de Evolución'];
        $rows[] = ['Período', 'Ingresos Netos', 'Egresos Devengados', 'Resultado Neto'];
        if (empty($evolution->series)) {
            $rows[] = ['Sin datos de evolución', '0.00', '0.00', '0.00'];
        } else {
            foreach ($evolution->series as $point) {
                $rows[] = [$point->period, $point->net_revenue, $point->accrued_expenses, $point->net_result];
            }
        }
        $rows[] = [];

        // 5. Indicadores Operativos (EPIC-21)
        $rows[] = ['4. INDICADORES OPERATIVOS COMPLEMENTARIOS'];
        $rows[] = ['Pedidos Web Pendientes de Preparación / Entrega', $operational->pending_web_orders_count];
        $rows[] = [];

        $rows[] = ['Ranking de Artículos Más Vendidos'];
        $rows[] = ['Posición', 'Artículo', 'Cantidad Vendida', 'Total Facturado'];
        if (empty($operational->top_articles)) {
            $rows[] = ['-', 'No se registraron ventas en el período', 0, '0.00'];
        } else {
            foreach ($operational->top_articles as $index => $article) {
                $rows[] = [$index + 1, $article->description, $article->quantity, $article->total_invoiced];
            }
        }
        $rows[] = [];

        $rows[] = ['Alertas de Stock Crítico y Conflictos'];
        $rows[] = ['Artículo', 'Depósito', 'Existencia Actual', 'Estado de Conflicto'];
        if (empty($operational->stock_alerts)) {
            $rows[] = ['-', 'No hay alertas de stock en los depósitos consultados', '-', '-'];
        } else {
            foreach ($operational->stock_alerts as $alert) {
                $rows[] = [
                    $alert->article_description,
                    $alert->warehouse_name,
                    $alert->quantity,
                    $alert->has_stock_conflict ? 'Conflicto por venta sin stock' : 'Sin stock',
                ];
            }
        }
        $rows[] = [];

        $rows[] = ['Ranking de Compras por Proveedor'];
        $rows[] = ['Posición', 'Proveedor', 'Total Compras', 'Saldo Cta. Cte.'];
        if (empty($operational->supplier_ranking)) {
            $rows[] = ['-', 'No se registraron compras en el período', '0.00', '0.00'];
        } else {
            foreach ($operational->supplier_ranking as $index => $supplier) {
                $rows[] = [
                    $index + 1,
                    $supplier->name,
                    $supplier->purchases_total,
                    $supplier->outstanding_balance,
                ];
            }
        }

        return $rows;
    }
}
