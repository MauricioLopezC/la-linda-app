<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ConsultStockMovements
{
    /**
     * Consult the paginated stock movement history with optional filters.
     *
     * @param  array{search?: ?string, warehouse_id?: ?int, stock_movement_type_id?: ?int, user_id?: ?int, date_from?: ?string, date_to?: ?string}  $filters
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public function execute(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->buildQuery($filters)->paginate($perPage)->withQueryString();
    }

    /**
     * Build the filtered, sorted query used to list stock movements.
     *
     * @param  array{search?: ?string, warehouse_id?: ?int, stock_movement_type_id?: ?int, user_id?: ?int, date_from?: ?string, date_to?: ?string}  $filters
     * @return Builder<StockMovement>
     */
    private function buildQuery(array $filters = []): Builder
    {
        return $this->applyFilters(StockMovement::query(), $filters)
            ->with([
                'type',
                'warehouse.branch',
                'user',
                'items.article.unitOfMeasure',
            ])
            ->withCount('items')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Apply optional filters to the query.
     *
     * @param  Builder<StockMovement>  $query
     * @param  array{search?: ?string, warehouse_id?: ?int, stock_movement_type_id?: ?int, user_id?: ?int, date_from?: ?string, date_to?: ?string}  $filters
     * @return Builder<StockMovement>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(! empty($filters['warehouse_id']), function (Builder $query) use ($filters) {
                $query->where('warehouse_id', (int) $filters['warehouse_id']);
            })
            ->when(! empty($filters['stock_movement_type_id']), function (Builder $query) use ($filters) {
                $query->where('stock_movement_type_id', (int) $filters['stock_movement_type_id']);
            })
            ->when(! empty($filters['user_id']), function (Builder $query) use ($filters) {
                $query->where('user_id', (int) $filters['user_id']);
            })
            ->when(! empty($filters['date_from']), function (Builder $query) use ($filters) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            })
            ->when(! empty($filters['date_to']), function (Builder $query) use ($filters) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            })
            ->when(! empty($filters['search']), function (Builder $query) use ($filters) {
                $search = trim((string) $filters['search']);
                $lowerSearch = mb_strtolower($search);
                $query->whereHas('items.article', function (Builder $q) use ($lowerSearch) {
                    $q->whereRaw('LOWER(description) LIKE ?', ["%{$lowerSearch}%"])
                        ->orWhereRaw('LOWER(internal_code) LIKE ?', ["%{$lowerSearch}%"])
                        ->orWhereRaw('LOWER(barcode) LIKE ?', ["%{$lowerSearch}%"]);
                });
            });
    }
}
