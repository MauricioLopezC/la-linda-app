<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\GetSupplierAccountStatement;
use App\Data\Purchasing\SupplierAccountStatementItemData;
use App\Data\Purchasing\SupplierAccountStatementTotalsData;
use App\Data\Purchasing\SupplierOptionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\ShowSupplierAccountStatementRequest;
use App\Models\Purchasing\Supplier;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierAccountStatementController extends Controller
{
    /** @var array<int, string> */
    private const HEADINGS = [
        'Fecha de emisión',
        'Vencimiento',
        'Tipo',
        'Comprobante',
        'Importe',
        'Pagado',
        'Saldo',
        'Antigüedad (días)',
        'Vencido',
    ];

    public function index(ShowSupplierAccountStatementRequest $request, GetSupplierAccountStatement $action): Response
    {
        $filters = $request->validated();
        $supplier = isset($filters['supplier_id']) ? Supplier::find((int) $filters['supplier_id']) : null;
        $statement = $supplier !== null ? $action->execute($supplier, $filters) : null;

        return Inertia::render('purchasing/account-statement/index', [
            'suppliers' => SupplierOptionData::collect(
                Supplier::query()->select(['id', 'business_name', 'tax_id'])->orderBy('business_name')->get()
            ),
            'totals' => $statement['totals'] ?? null,
            'items' => $statement !== null ? SupplierAccountStatementItemData::collect($statement['items']) : [],
            'filters' => [
                'supplier_id' => isset($filters['supplier_id']) ? (string) $filters['supplier_id'] : '',
                'date_from' => (string) ($filters['date_from'] ?? ''),
                'date_to' => (string) ($filters['date_to'] ?? ''),
            ],
        ]);
    }

    /**
     * Export the account statement to CSV.
     */
    public function exportCsv(ShowSupplierAccountStatementRequest $request, GetSupplierAccountStatement $action): StreamedResponse
    {
        $supplier = $this->resolveSupplierOrFail($request);
        $statement = $action->execute($supplier, $request->validated());
        $filename = 'cuenta-corriente-'.Str::slug($supplier->business_name).'.csv';

        return response()->streamDownload(function () use ($statement) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            foreach ($this->headerRows($statement['totals']) as $row) {
                fputcsv($handle, $row, ';');
            }
            fputcsv($handle, [], ';');
            fputcsv($handle, self::HEADINGS, ';');

            foreach ($statement['items'] as $item) {
                fputcsv($handle, $this->itemRow($item), ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Export the account statement to Excel (XLSX).
     */
    public function exportExcel(ShowSupplierAccountStatementRequest $request, GetSupplierAccountStatement $action): BinaryFileResponse
    {
        $supplier = $this->resolveSupplierOrFail($request);
        $statement = $action->execute($supplier, $request->validated());
        $filename = 'cuenta-corriente-'.Str::slug($supplier->business_name).'.xlsx';
        $tempPath = tempnam(sys_get_temp_dir(), 'export_') ?: sys_get_temp_dir().'/export_'.uniqid().'.xlsx';

        $writer = new Writer;
        $writer->openToFile($tempPath);

        foreach ($this->headerRows($statement['totals']) as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(self::HEADINGS));

        foreach ($statement['items'] as $item) {
            $writer->addRow(Row::fromValues($this->itemRow($item)));
        }

        $writer->close();

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** @return array<int, array<int, string>> */
    private function headerRows(SupplierAccountStatementTotalsData $totals): array
    {
        return [
            ['Proveedor', $totals->supplier_business_name],
            ['Total recibido', $totals->total_received],
            ['Total pagado', $totals->total_paid],
            ['Saldo', $totals->balance],
        ];
    }

    /** @return array<int, string|int> */
    private function itemRow(SupplierAccountStatementItemData $item): array
    {
        return [
            $item->issue_date_formatted,
            $item->due_date_formatted ?? '',
            $item->type_label,
            $item->formatted_number,
            $item->total_amount,
            $item->paid_amount,
            $item->balance,
            $item->aging_days,
            $item->is_overdue ? 'Sí' : 'No',
        ];
    }

    private function resolveSupplierOrFail(ShowSupplierAccountStatementRequest $request): Supplier
    {
        $filters = $request->validated();

        if (! isset($filters['supplier_id'])) {
            abort(422, 'Debe seleccionar un proveedor para exportar su cuenta corriente.');
        }

        return Supplier::findOrFail((int) $filters['supplier_id']);
    }
}
