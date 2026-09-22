<?php

namespace App\Actions\Pricing;

use App\Enums\Catalog\ArticleStatus;
use App\Models\Catalog\Article;
use App\Models\Pricing\PriceList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * List the articles that can be priced in a given list, together with the price they already have.
 *
 * The base query is Articles, not PriceListItems: an article without a price is the absence of a
 * row, so the "articles without a price" filter the story asks for is only reachable by starting
 * from the catalog and left joining the prices of this list.
 */
class ConsultPriceListArticles
{
    /**
     * @param  array{search?: ?string, category_id?: ?int, price_status?: ?string}  $filters
     * @return LengthAwarePaginator<int, Article>
     */
    public function execute(PriceList $priceList, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->buildQuery($priceList, $filters)->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array{search?: ?string, category_id?: ?int, price_status?: ?string}  $filters
     * @return Builder<Article>
     */
    private function buildQuery(PriceList $priceList, array $filters): Builder
    {
        $query = Article::query()
            ->leftJoin('price_list_items', function (JoinClause $join) use ($priceList): void {
                $join->on('price_list_items.article_id', '=', 'articles.id')
                    ->where('price_list_items.price_list_id', '=', $priceList->id);
            })
            ->with(['category', 'brand', 'unitOfMeasure'])
            ->select([
                'articles.*',
                'price_list_items.id as price_list_item_id',
                'price_list_items.price as price_list_item_price',
            ]);

        return $this->applyFilters($query, $filters)
            ->orderBy('articles.description')
            ->orderBy('articles.id');
    }

    /**
     * Inactive articles are out of scope, except the ones that already hold a price in this list:
     * an article deactivated after being priced has to stay visible so the stale price can be
     * removed instead of silently lingering in the price resolution cascade (HU-056).
     *
     * @param  Builder<Article>  $query
     * @param  array{search?: ?string, category_id?: ?int, price_status?: ?string}  $filters
     * @return Builder<Article>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->where(function (Builder $query): void {
                $query->where('articles.status', ArticleStatus::Active)
                    ->orWhereNotNull('price_list_items.id');
            })
            ->when(! empty($filters['search']), function (Builder $query) use ($filters): void {
                $search = mb_strtolower(trim((string) $filters['search']));
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereRaw('LOWER(articles.internal_code) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(articles.description) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(articles.barcode) LIKE ?', ["%{$search}%"]);
                });
            })
            ->when(! empty($filters['category_id']), function (Builder $query) use ($filters): void {
                $query->where('articles.category_id', (int) $filters['category_id']);
            })
            ->when(! empty($filters['price_status']) && $filters['price_status'] !== 'all', function (Builder $query) use ($filters): void {
                match ($filters['price_status']) {
                    'with_price' => $query->whereNotNull('price_list_items.id'),
                    'without_price' => $query->whereNull('price_list_items.id'),
                    default => $query,
                };
            });
    }
}
