<?php

namespace App\Actions\Ecommerce;

use App\Enums\Ecommerce\OnlineAvailabilityStatus;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Catalog\Article;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GetOnlineAvailability
{
    /**
     * Calculate online availability for a single article.
     */
    public function forArticle(Article|int $article): OnlineAvailabilityResult
    {
        $articleId = $article instanceof Article ? $article->id : $article;
        $results = $this->forArticles([$articleId]);

        return $results[$articleId] ?? new OnlineAvailabilityResult(
            availableQuantity: '0.000',
            numericQuantity: 0.0,
            status: OnlineAvailabilityStatus::OutOfStock,
            isAvailable: false,
        );
    }

    /**
     * Batch calculate online availability for multiple articles in 2 grouped queries (no N+1).
     *
     * @param  array<int, Article|int>|Collection<int, Article|int>  $articles
     * @return array<int, OnlineAvailabilityResult> Map of article_id => OnlineAvailabilityResult
     */
    public function forArticles(array|Collection $articles): array
    {
        $articleIds = [];
        foreach ($articles as $item) {
            $articleIds[] = $item instanceof Article ? $item->id : (int) $item;
        }
        $articleIds = array_values(array_unique(array_filter($articleIds)));

        if (empty($articleIds)) {
            return [];
        }

        $onlineWarehouse = Warehouse::query()->online()->active()->first();

        if ($onlineWarehouse === null) {
            $results = [];
            foreach ($articleIds as $id) {
                $results[$id] = new OnlineAvailabilityResult(
                    availableQuantity: '0.000',
                    numericQuantity: 0.0,
                    status: OnlineAvailabilityStatus::OutOfStock,
                    isAvailable: false,
                );
            }

            return $results;
        }

        // Query 1: Physical balances in the active online warehouse
        /** @var array<int, string|float> $physicalBalances */
        $physicalBalances = StockBalance::query()
            ->where('warehouse_id', $onlineWarehouse->id)
            ->whereIn('article_id', $articleIds)
            ->pluck('quantity', 'article_id')
            ->all();

        // Query 2: Committed quantities in paid or in-preparation web orders awaiting warehouse dispatch
        /** @var array<int, string|float> $committedBalances */
        $committedBalances = WebOrderItem::query()
            ->selectRaw('article_id, sum(quantity) as total_committed')
            ->whereIn('article_id', $articleIds)
            ->whereHas('webOrder', function (Builder $query): void {
                $query->whereIn('status', [
                    WebOrderStatus::Paid->value,
                    WebOrderStatus::InPreparation->value,
                ]);
            })
            ->groupBy('article_id')
            ->pluck('total_committed', 'article_id')
            ->all();

        $results = [];
        foreach ($articleIds as $id) {
            $physical = (float) ($physicalBalances[$id] ?? 0.0);
            $committed = (float) ($committedBalances[$id] ?? 0.0);
            $available = max(0.0, $physical - $committed);
            $status = $this->resolveStatus($available);

            $results[$id] = new OnlineAvailabilityResult(
                availableQuantity: number_format($available, 3, '.', ''),
                numericQuantity: $available,
                status: $status,
                isAvailable: $status !== OnlineAvailabilityStatus::OutOfStock,
            );
        }

        return $results;
    }

    /**
     * Determine the availability status enum from the numeric available quantity.
     */
    private function resolveStatus(float $available): OnlineAvailabilityStatus
    {
        if ($available <= 0.0) {
            return OnlineAvailabilityStatus::OutOfStock;
        }

        $lowStockThreshold = (float) config('ecommerce.low_stock_threshold', 5.0);

        if ($available <= $lowStockThreshold) {
            return OnlineAvailabilityStatus::LastUnits;
        }

        return OnlineAvailabilityStatus::Available;
    }
}
