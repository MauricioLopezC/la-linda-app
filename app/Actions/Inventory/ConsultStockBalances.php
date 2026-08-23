<?php

namespace App\Actions\Inventory;

use App\Data\Inventory\StockBranchTotalData;
use App\Data\Inventory\StockTotalsData;
use App\Models\Inventory\StockBalance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ConsultStockBalances
{
    /**
     * Consult and calculate stock balances and aggregated totals.
     *
     * @param  array{search?: ?string, category_id?: ?int, warehouse_id?: ?int, status?: ?string}  $filters
     * @return array{stocks: Collection<int, StockBalance>, totals: StockTotalsData}
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
     * Build the filtered query for stock balances.
     *
     * @param  array{search?: ?string, category_id?: ?int, warehouse_id?: ?int, status?: ?string}  $filters
     * @return Builder<StockBalance>
     */
    public function buildQuery(array $filters = []): Builder
    {
        return StockBalance::query()
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
                    default => $query,
                };
            })
            ->join('articles', 'stock_balances.article_id', '=', 'articles.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->join('branches', 'warehouses.branch_id', '=', 'branches.id')
            ->orderBy('branches.name')
            ->orderBy('warehouses.name')
            ->orderBy('articles.internal_code')
            ->select('stock_balances.*');
    }

    /**
     * Calculate global and branch-level consolidated inventory totals.
     *
     * @param  Collection<int, StockBalance>  $stocks
     */
    private function calculateTotals(Collection $stocks): StockTotalsData
    {
        $grandTotalItems = $stocks->count();
        $totalInStock = 0;
        $totalOutOfStock = 0;

        /** @var array<int, array{branch_id: int, branch_name: string, total_items: int, in_stock_count: int, out_of_stock_count: int}> $branchGroups */
        $branchGroups = [];

        foreach ($stocks as $stock) {
            $qty = (float) $stock->quantity;
            $isOutOfStock = $qty <= 0;

            if ($isOutOfStock) {
                $totalOutOfStock++;
            } else {
                $totalInStock++;
            }

            $branch = $stock->warehouse->branch;
            $branchId = $branch->id;

            if (! isset($branchGroups[$branchId])) {
                $branchGroups[$branchId] = [
                    'branch_id' => $branchId,
                    'branch_name' => $branch->name,
                    'total_items' => 0,
                    'in_stock_count' => 0,
                    'out_of_stock_count' => 0,
                ];
            }

            $branchGroups[$branchId]['total_items']++;

            if ($isOutOfStock) {
                $branchGroups[$branchId]['out_of_stock_count']++;
            } else {
                $branchGroups[$branchId]['in_stock_count']++;
            }
        }

        $branchTotals = array_map(
            fn (array $group): StockBranchTotalData => new StockBranchTotalData(
                branch_id: $group['branch_id'],
                branch_name: $group['branch_name'],
                total_items: $group['total_items'],
                in_stock_count: $group['in_stock_count'],
                out_of_stock_count: $group['out_of_stock_count'],
            ),
            array_values($branchGroups)
        );

        return new StockTotalsData(
            grand_total_items: $grandTotalItems,
            total_in_stock: $totalInStock,
            total_out_of_stock: $totalOutOfStock,
            branch_totals: $branchTotals,
        );
    }
}
