<?php

namespace App\Actions\Inventory;

use App\Data\Inventory\StockBranchTotalData;
use App\Data\Inventory\StockTotalsData;
use App\Models\Inventory\StockBalance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ConsultStockBalances
{
    /**
     * Consult the paginated stock balances and their aggregated totals.
     *
     * @param  array{search?: ?string, category_id?: ?int, warehouse_id?: ?int, status?: ?string}  $filters
     * @return array{stocks: LengthAwarePaginator<int, StockBalance>, totals: StockTotalsData}
     */
    public function execute(array $filters = [], int $perPage = 25): array
    {
        $stocks = $this->buildQuery($filters)->paginate($perPage)->withQueryString();
        $totals = $this->calculateTotals($filters);

        return [
            'stocks' => $stocks,
            'totals' => $totals,
        ];
    }

    /**
     * Build the filtered, sorted query used to list stock balances.
     *
     * @param  array{search?: ?string, category_id?: ?int, warehouse_id?: ?int, status?: ?string}  $filters
     * @return Builder<StockBalance>
     */
    private function buildQuery(array $filters = []): Builder
    {
        return $this->applyFilters(StockBalance::query(), $filters)
            ->with([
                'article.category',
                'article.brand',
                'article.unitOfMeasure',
                'warehouse.branch',
            ])
            ->select('stock_balances.*')
            ->orderBy('branches.name')
            ->orderBy('warehouses.name')
            ->orderBy('articles.internal_code');
    }

    /**
     * Join the tables filters need and apply them, so both the list and the totals query share
     * the exact same filtered rows without a redundant whereHas() subquery over an already-joined
     * table.
     *
     * @param  Builder<StockBalance>  $query
     * @param  array{search?: ?string, category_id?: ?int, warehouse_id?: ?int, status?: ?string}  $filters
     * @return Builder<StockBalance>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->join('articles', 'stock_balances.article_id', '=', 'articles.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->join('branches', 'warehouses.branch_id', '=', 'branches.id')
            ->when(! empty($filters['search']), function (Builder $query) use ($filters) {
                $search = trim((string) $filters['search']);
                $query->where(function (Builder $query) use ($search) {
                    $query->where('articles.internal_code', 'like', "%{$search}%")
                        ->orWhere('articles.description', 'like', "%{$search}%")
                        ->orWhere('articles.barcode', 'like', "%{$search}%");
                });
            })
            ->when(! empty($filters['category_id']), function (Builder $query) use ($filters) {
                $query->where('articles.category_id', (int) $filters['category_id']);
            })
            ->when(! empty($filters['warehouse_id']), function (Builder $query) use ($filters) {
                $query->where('stock_balances.warehouse_id', (int) $filters['warehouse_id']);
            })
            ->when(! empty($filters['status']) && $filters['status'] !== 'all', function (Builder $query) use ($filters) {
                match ($filters['status']) {
                    'in_stock' => $query->where('stock_balances.quantity', '>', 0),
                    'out_of_stock' => $query->where('stock_balances.quantity', '<=', 0),
                    default => $query,
                };
            });
    }

    /**
     * Calculate global and branch-level consolidated inventory totals with a single grouped
     * aggregate query, instead of loading every matching row into PHP to count them.
     *
     * @param  array{search?: ?string, category_id?: ?int, warehouse_id?: ?int, status?: ?string}  $filters
     */
    private function calculateTotals(array $filters): StockTotalsData
    {
        $branchRows = $this->applyFilters(StockBalance::query(), $filters)
            ->toBase()
            ->selectRaw(
                'branches.id as branch_id, branches.name as branch_name, '.
                'count(*) as total_items, '.
                'sum(case when stock_balances.quantity > 0 then 1 else 0 end) as in_stock_count, '.
                'sum(case when stock_balances.quantity <= 0 then 1 else 0 end) as out_of_stock_count'
            )
            ->groupBy('branches.id', 'branches.name')
            ->orderBy('branches.name')
            ->get();

        $branchTotals = $branchRows
            ->map(fn (object $row): StockBranchTotalData => new StockBranchTotalData(
                branch_id: (int) $row->branch_id,
                branch_name: $row->branch_name,
                total_items: (int) $row->total_items,
                in_stock_count: (int) $row->in_stock_count,
                out_of_stock_count: (int) $row->out_of_stock_count,
            ))
            ->all();

        return new StockTotalsData(
            grand_total_items: (int) $branchRows->sum('total_items'),
            total_in_stock: (int) $branchRows->sum('in_stock_count'),
            total_out_of_stock: (int) $branchRows->sum('out_of_stock_count'),
            branch_totals: $branchTotals,
        );
    }
}
