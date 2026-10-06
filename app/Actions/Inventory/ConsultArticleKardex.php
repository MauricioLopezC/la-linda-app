<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\StockMovementItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ConsultArticleKardex
{
    /**
     * Consult the kardex (stock ledger) of one article in one warehouse: every movement line, newest
     * first, with the running balance right after it.
     *
     * The balance is a window sum over the stored signed deltas of the whole article/warehouse
     * history, computed before the optional filters narrow the rows, so a filtered row still shows
     * the real balance at that point in time.
     *
     * @param  array{stock_movement_type_id?: ?int, user_id?: ?int, date_from?: ?string, date_to?: ?string}  $filters
     * @return LengthAwarePaginator<int, StockMovementItem>
     */
    public function execute(int $articleId, int $warehouseId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $ledger = StockMovementItem::query()
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_items.stock_movement_id')
            ->where('stock_movement_items.article_id', $articleId)
            ->where('stock_movements.warehouse_id', $warehouseId)
            ->select([
                'stock_movement_items.id',
                'stock_movement_items.stock_movement_id',
                'stock_movement_items.article_id',
                'stock_movement_items.quantity',
                'stock_movement_items.system_quantity',
                'stock_movements.stock_movement_type_id',
                'stock_movements.user_id',
                'stock_movements.created_at',
            ])
            ->selectRaw('SUM(stock_movement_items.quantity) OVER (ORDER BY stock_movements.created_at, stock_movements.id, stock_movement_items.id) AS balance');

        return $this->applyFilters(StockMovementItem::query()->fromSub($ledger, 'stock_movement_items'), $filters)
            ->with([
                'stockMovement.type',
                'stockMovement.user',
                'stockMovement.supplierVoucher',
                'stockMovement.reversalOf.supplierVoucher',
                'stockMovement.sale',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('stock_movement_id')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Apply optional filters to the ledger rows.
     *
     * @param  Builder<StockMovementItem>  $query
     * @param  array{stock_movement_type_id?: ?int, user_id?: ?int, date_from?: ?string, date_to?: ?string}  $filters
     * @return Builder<StockMovementItem>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
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
            });
    }
}
