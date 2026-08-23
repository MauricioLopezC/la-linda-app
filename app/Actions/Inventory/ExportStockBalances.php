<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\StockBalance;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportStockBalances
{
    /**
     * Stream CSV file containing the stock balances report with UTF-8 BOM for Excel compatibility.
     *
     * @param  Collection<int, StockBalance>  $stocks
     */
    public function execute(Collection $stocks): StreamedResponse
    {
        $filename = 'existencias-'.now()->format('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->streamDownload(function () use ($stocks): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Write UTF-8 BOM for seamless Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Headers
            fputcsv($handle, [
                'Código',
                'Artículo',
                'Categoría',
                'Marca',
                'Unidad de Medida',
                'Sucursal',
                'Depósito',
                'Existencia',
                'Estado',
            ], ';');

            foreach ($stocks as $stock) {
                $qty = (float) $stock->quantity;
                $statusLabel = $qty <= 0 ? 'Sin stock' : 'En stock';

                fputcsv($handle, [
                    $stock->article->internal_code,
                    $stock->article->description,
                    $stock->article->category->name,
                    $stock->article->brand !== null ? $stock->article->brand->name : '—',
                    $stock->article->unitOfMeasure->name,
                    $stock->warehouse->branch->name,
                    $stock->warehouse->name,
                    number_format($qty, 3, ',', ''),
                    $statusLabel,
                ], ';');
            }

            fclose($handle);
        }, $filename, $headers);
    }
}
