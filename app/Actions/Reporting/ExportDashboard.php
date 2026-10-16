<?php

namespace App\Actions\Reporting;

use App\Enums\Sales\SaleChannel;
use App\Models\Organization\Branch;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @phpstan-type ExportNumber array{value: string|float, format: non-empty-string, csv: string}
 * @phpstan-type ExportValue string|int|ExportNumber
 */
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
                $values = array_map(function (string|int|array $value): string|int {
                    if (is_array($value)) {
                        return $value['csv'];
                    }

                    return is_string($value) ? $this->safeCsvText($value) : $value;
                }, $row);

                fputcsv($handle, $values, ';', escape: '');
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
            $cells = array_map(function (string|int|array $value): Cell {
                if (is_array($value)) {
                    return new NumericCell((float) $value['value'], new Style(format: $value['format']));
                }

                return is_int($value) ? new NumericCell($value) : new StringCell($value);
            }, $row);

            $writer->addRow(new Row($cells));
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
     * @return list<list<ExportValue>>
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

        /** @var list<list<ExportValue>> $rows */
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
        $rows[] = ['Total Facturado', $this->decimal($revenues->total_invoiced)];
        $rows[] = ['Ingresos Netos (sin IVA)', $this->decimal($revenues->net_revenue)];
        $rows[] = ['IVA Total', $this->decimal($revenues->vat_total)];
        $rows[] = ['Ventas Confirmadas (cantidad)', $revenues->confirmed_sales_count];
        $rows[] = ['Ticket Promedio', $this->decimal($revenues->average_ticket)];
        $rows[] = ['Variación Total Facturado (%)', $this->percentage($revenues->total_invoiced_variation_pct, signed: true)];
        $rows[] = [];

        $rows[] = ['Desglose de Ingresos por Canal'];
        $rows[] = ['Canal', 'Cantidad de Ventas', 'Monto Facturado'];
        if (empty($revenues->by_channel)) {
            $rows[] = ['Sin datos en el período', 0, $this->decimal('0.00')];
        } else {
            foreach ($revenues->by_channel as $channelItem) {
                $rows[] = [$channelItem->label, $channelItem->count, $this->decimal($channelItem->amount)];
            }
        }
        $rows[] = [];

        $rows[] = ['Desglose de Ingresos por Sucursal'];
        $rows[] = ['Sucursal', 'Cantidad de Ventas', 'Monto Facturado'];
        if (empty($revenues->by_branch)) {
            $rows[] = ['Sin datos en el período', 0, $this->decimal('0.00')];
        } else {
            foreach ($revenues->by_branch as $branchItem) {
                $rows[] = [$branchItem->label, $branchItem->count, $this->decimal($branchItem->amount)];
            }
        }
        $rows[] = [];

        $rows[] = ['Desglose de Ingresos por Medio de Pago'];
        $rows[] = ['Medio de Pago', 'Cantidad de Operaciones', 'Monto Cobrado'];
        if (empty($revenues->by_payment_method)) {
            $rows[] = ['Sin datos en el período', 0, $this->decimal('0.00')];
        } else {
            foreach ($revenues->by_payment_method as $methodItem) {
                $rows[] = [$methodItem->label, $methodItem->count, $this->decimal($methodItem->amount)];
            }
        }
        $rows[] = [];

        // 3. Resumen de Egresos (EPIC-19)
        $rows[] = ['2. RESUMEN DE EGRESOS'];
        $rows[] = ['Comprobantes Recibidos (Devengado Proveedores)', $this->decimal($expenses->accrued_total)];
        $rows[] = ['Pagado a Proveedores (Órdenes Emitidas)', $this->decimal($expenses->paid_to_suppliers_total)];
        $rows[] = ['Egresos Operativos de Caja', $this->decimal($expenses->cash_expenses_total)];
        $rows[] = ['Desembolso Total Efectivo', $this->decimal($expenses->disbursed_total)];
        $rows[] = [];

        $rows[] = ['Desglose de Pagos a Proveedores por Medio de Pago'];
        $rows[] = ['Medio de Pago', 'Cantidad de Pagos', 'Monto Pagado'];
        if (empty($expenses->by_payment_method)) {
            $rows[] = ['Sin pagos a proveedores en el período', 0, $this->decimal('0.00')];
        } else {
            foreach ($expenses->by_payment_method as $methodItem) {
                $rows[] = [$methodItem->label, $methodItem->count, $this->decimal($methodItem->amount)];
            }
        }
        $rows[] = [];

        // 4. Resultado y Evolución Temporal (EPIC-20)
        $rows[] = ['3. RESULTADO Y EVOLUCIÓN TEMPORAL'];
        $rows[] = ['Resultado Neto del Período', $this->decimal($evolution->net_result)];
        $rows[] = ['Margen Neto (%)', $this->percentage($evolution->net_margin_pct)];
        $rows[] = ['Variación Período Anterior (%)', $this->percentage($evolution->net_result_variation_pct, signed: true)];
        $rows[] = [];

        $rows[] = ['Serie Temporal de Evolución'];
        $rows[] = ['Período', 'Ingresos Netos', 'Egresos Devengados', 'Resultado Neto'];
        if (empty($evolution->series)) {
            $rows[] = ['Sin datos de evolución', $this->decimal('0.00'), $this->decimal('0.00'), $this->decimal('0.00')];
        } else {
            foreach ($evolution->series as $point) {
                $rows[] = [$point->period, $this->decimal($point->net_revenue), $this->decimal($point->accrued_expenses), $this->decimal($point->net_result)];
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
            $rows[] = ['-', 'No se registraron ventas en el período', $this->decimal('0.000', 3), $this->decimal('0.00')];
        } else {
            foreach ($operational->top_articles as $index => $article) {
                $rows[] = [$index + 1, $article->description, $this->decimal($article->quantity, 3), $this->decimal($article->total_invoiced)];
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
                    $this->decimal($alert->quantity, 3),
                    $alert->has_stock_conflict ? 'Conflicto por venta sin stock' : 'Sin stock',
                ];
            }
        }
        $rows[] = [];

        $rows[] = ['Ranking de Compras por Proveedor'];
        $rows[] = ['Posición', 'Proveedor', 'Total Compras', 'Saldo Cta. Cte.'];
        if (empty($operational->supplier_ranking)) {
            $rows[] = ['-', 'No se registraron compras en el período', $this->decimal('0.00'), $this->decimal('0.00')];
        } else {
            foreach ($operational->supplier_ranking as $index => $supplier) {
                $rows[] = [
                    $index + 1,
                    $supplier->name,
                    $this->decimal($supplier->purchases_total),
                    $this->decimal($supplier->outstanding_balance),
                ];
            }
        }

        return $rows;
    }

    /** @return ExportNumber */
    private function decimal(string $value, int $decimals = 2): array
    {
        return ['value' => $value, 'format' => '0.'.str_repeat('0', $decimals), 'csv' => $value];
    }

    /** @return ExportNumber|string */
    private function percentage(?float $value, bool $signed = false): array|string
    {
        if ($value === null) {
            return 'N/D';
        }

        return [
            'value' => $value / 100,
            'format' => $signed ? '+0.0%;-0.0%;0.0%' : '0.0%',
            'csv' => ($signed && $value > 0 ? '+' : '').$value.'%',
        ];
    }

    private function safeCsvText(string $value): string
    {
        if (preg_match('/^[\p{Z}\x00-\x20]*[=+\-@\t\r\n＝＋－＠]/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
