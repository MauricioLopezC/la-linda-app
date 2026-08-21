<?php

namespace App\Actions\Inventory;

use App\Data\Inventory\StockBranchTotalData;
use App\Data\Inventory\StockTotalsData;
use App\Models\Inventory\WarehouseStock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ConsultStockBalances
{
    /**
     * Consult and calculate stock balances and aggregated totals.
     *
     * @param  array{search?: ?string, category_id?: ?int, warehouse_id?: ?int, status?: ?string}  $filters
     * @return array{stocks: Collection<int, WarehouseStock>, totals: StockTotalsData}
     */
    public function execute(array $filters = []): array
    {
        $query = $this->buildQuery($filters);
        $stocks = $query->get();

        $totals = $this->calculateTotals($stocks);

        return [
            'stocks' => $stocks,
            'totals' => $totals,
        ];
    }

    /**
     * Build the filtered query for warehouse stocks.
     *
     * @param  array{search?: ?string, category_id?: ?int, warehouse_id?: ?int, status?: ?string}  $filters
     * @return Builder<WarehouseStock>
     */
    public function buildQuery(array $filters = []): Builder
    {
        return WarehouseStock::query()
            ->with([
                'article.category',
                'article.brand',
                'article.unitOfMeasure',
                'warehouse.branch',
            ])
            ->when(! empty($filters['search']), function (Builder $query) use ($filters) {
                $search = trim((string) $filters['search']);
                $query->whereHas('article', function (Builder $articleQuery) use ($search) {
                    $articleQuery->where('internal_code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->when(! empty($filters['category_id']), function (Builder $query) use ($filters) {
                $query->whereHas('article', function (Builder $articleQuery) use ($filters) {
                    $articleQuery->where('category_id', (int) $filters['category_id']);
                });
            })
            ->when(! empty($filters['warehouse_id']), function (Builder $query) use ($filters) {
                $query->where('warehouse_id', (int) $filters['warehouse_id']);
            })
            ->when(! empty($filters['status']) && $filters['status'] !== 'all', function (Builder $query) use ($filters) {
                match ($filters['status']) {
                    'in_stock' => $query->where('quantity', '>', 0),
                    'out_of_stock' => $query->where('quantity', '<=', 0),
                    'below_min' => $query->where('quantity', '>', 0)->whereColumn('quantity', '<=', 'min_stock'),
                    default => $query,
                };
            })
            ->join('articles', 'warehouse_stocks.article_id', '=', 'articles.id')
            ->join('warehouses', 'warehouse_stocks.warehouse_id', '=', 'warehouses.id')
            ->join('branches', 'warehouses.branch_id', '=', 'branches.id')
            ->orderBy('branches.name')
            ->orderBy('warehouses.name')
            ->orderBy('articles.internal_code')
            ->select('warehouse_stocks.*');
    }

    /**
     * Calculate global and branch-level totals.
     *
     * @param  Collection<int, WarehouseStock>  $stocks
     */
    private function calculateTotals(Collection $stocks): StockTotalsData
    {
        $grandTotalQuantity = 0.0;
        $grandTotalItems = $stocks->count();
        $totalLowStock = 0;
        $totalOutOfStock = 0;

        /** @var array<int, array{branch_id: int, branch_name: string, total_quantity: float, total_items: int, low_stock_count: int, out_of_stock_count: int}> $branchGroups */
        $branchGroups = [];

        foreach ($stocks as $stock) {
            $grandTotalQuantity += $stock->quantity;

            if ($stock->isOutOfStock()) {
                $totalOutOfStock++;
            } elseif ($stock->isBelowMinimum()) {
                $totalLowStock++;
            }

            $branch = $stock->warehouse->branch;
            $branchId = $branch->id;

            if (! isset($branchGroups[$branchId])) {
                $branchGroups[$branchId] = [
                    'branch_id' => $branchId,
                    'branch_name' => $branch->name,
                    'total_quantity' => 0.0,
                    'total_items' => 0,
                    'low_stock_count' => 0,
                    'out_of_stock_count' => 0,
                ];
            }

            $branchGroups[$branchId]['total_quantity'] += $stock->quantity;
            $branchGroups[$branchId]['total_items']++;

            if ($stock->isOutOfStock()) {
                $branchGroups[$branchId]['out_of_stock_count']++;
            } elseif ($stock->isBelowMinimum()) {
                $branchGroups[$branchId]['low_stock_count']++;
            }
        }

        $branchTotals = array_map(
            fn (array $group): StockBranchTotalData => new StockBranchTotalData(
                branch_id: $group['branch_id'],
                branch_name: $group['branch_name'],
                total_quantity: round($group['total_quantity'], 2),
                total_items: $group['total_items'],
                low_stock_count: $group['low_stock_count'],
                out_of_stock_count: $group['out_of_stock_count'],
            ),
            array_values($branchGroups)
        );

        return new StockTotalsData(
            grand_total_quantity: round($grandTotalQuantity, 2),
            grand_total_items: $grandTotalItems,
            total_low_stock: $totalLowStock,
            total_out_of_stock: $totalOutOfStock,
            branch_totals: $branchTotals,
        );
    }
}
