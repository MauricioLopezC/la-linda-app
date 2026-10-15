<?php

namespace App\Actions\Ecommerce;

use App\Actions\Pricing\ResolveArticlePrice;
use App\Data\Ecommerce\OnlineCatalogArticleData;
use App\Enums\Catalog\ArticleStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Pricing\PriceListScope;
use App\Enums\Pricing\PriceListValidityStatus;
use App\Models\Catalog\Article;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Customers\Customer;
use App\Models\Pricing\PriceList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConsultOnlineCatalog
{
    public function __construct(
        private readonly ResolveArticlePrice $resolveArticlePrice,
    ) {}

    /**
     * @param  array{search?: ?string, category_id?: ?int, brand_id?: ?int, min_price?: float|int|string|null, max_price?: float|int|string|null, sort?: ?string}  $filters
     * @param  array<int, int>|null  $applicableListIds
     * @return LengthAwarePaginator<int, OnlineCatalogArticleData>
     */
    public function execute(
        array $filters = [],
        ?Customer $customer = null,
        int $perPage = 16,
        ?array $applicableListIds = null,
    ): LengthAwarePaginator {
        $applicableListIds ??= $this->resolveApplicablePriceListIds($customer);

        // If no active, currently-valid price lists exist in the cascade,
        // no articles can have a valid price.
        if (empty($applicableListIds)) {
            /** @var LengthAwarePaginator<int, OnlineCatalogArticleData> $emptyPaginator */
            $emptyPaginator = new LengthAwarePaginator(
                items: [],
                total: 0,
                perPage: $perPage,
                currentPage: 1,
                options: ['path' => LengthAwarePaginator::resolveCurrentPath()]
            );

            return $emptyPaginator;
        }

        $query = $this->buildQuery($filters, $applicableListIds);

        /** @var LengthAwarePaginator<int, Article> $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

        /** @var LengthAwarePaginator<int, OnlineCatalogArticleData> $transformed */
        $transformed = $paginator->through(function (Article $article) use ($customer): OnlineCatalogArticleData {
            $priceData = $this->resolveArticlePrice->execute(
                $article,
                PriceListChannel::Online,
                $customer,
            );

            return OnlineCatalogArticleData::fromArticleAndPrice($article, $priceData);
        });

        return $transformed;
    }

    /**
     * Resolve the IDs of all active, currently-valid price lists in the cascade:
     * 1. Customer's particular list (if logged in and valid)
     * 2. Online channel list
     * 3. General list (fallback)
     *
     * @return array<int, int>
     */
    public function resolveApplicablePriceListIds(?Customer $customer): array
    {
        $ids = [];

        // 1. Customer's particular list
        if ($customer !== null) {
            $customer->loadMissing('priceList');
            $particularList = $customer->priceList;

            if (
                $particularList !== null
                && $particularList->scope === PriceListScope::Particular
                && $particularList->is_active
                && $particularList->validityStatus() === PriceListValidityStatus::Vigente
            ) {
                $ids[] = $particularList->id;
            }
        }

        // 2. Channel list for Online
        $onlineList = PriceList::query()
            ->active()
            ->currentlyValid()
            ->forChannel(PriceListChannel::Online)
            ->first();

        if ($onlineList !== null) {
            $ids[] = $onlineList->id;
        }

        // 3. General list fallback
        $generalList = PriceList::query()
            ->active()
            ->currentlyValid()
            ->forChannel(PriceListChannel::General)
            ->first();

        if ($generalList !== null) {
            $ids[] = $generalList->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Fetch active brands that have at least one article publishable online with an effective price.
     *
     * @param  array<int, int>  $applicableListIds
     * @return Collection<int, Brand>
     */
    public function getAvailableBrands(array $applicableListIds): Collection
    {
        if (empty($applicableListIds)) {
            return collect();
        }

        return Brand::query()
            ->active()
            ->whereHas('articles', function (Builder $q) use ($applicableListIds): void {
                $q->where('status', ArticleStatus::Active)
                    ->where('is_online_publishable', true)
                    ->whereHas('priceListItems', function (Builder $pq) use ($applicableListIds): void {
                        $pq->whereIn('price_list_id', $applicableListIds);
                    })
                    ->whereHas('category', function (Builder $cq): void {
                        $cq->where('is_active', true);
                    });
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Correlated scalar subquery computing the effective price of each article
     * following the HU-056 cascade priority across applicable price lists.
     *
     * @param  array<int, int>  $orderedListIds
     * @return \Closure(mixed): void
     */
    private function getEffectivePriceSubqueryCallback(array $orderedListIds): \Closure
    {
        return function (mixed $subquery) use ($orderedListIds): void {
            $subquery->select('price_list_items.price')
                ->from('price_list_items')
                ->whereColumn('price_list_items.article_id', 'articles.id')
                ->whereIn('price_list_items.price_list_id', $orderedListIds);

            match (count($orderedListIds)) {
                0, 1 => null,
                2 => $subquery->orderByRaw(
                    'CASE WHEN price_list_items.price_list_id = ? THEN 1 WHEN price_list_items.price_list_id = ? THEN 2 ELSE 3 END ASC',
                    $orderedListIds
                ),
                3 => $subquery->orderByRaw(
                    'CASE WHEN price_list_items.price_list_id = ? THEN 1 WHEN price_list_items.price_list_id = ? THEN 2 WHEN price_list_items.price_list_id = ? THEN 3 ELSE 4 END ASC',
                    $orderedListIds
                ),
                4 => $subquery->orderByRaw(
                    'CASE WHEN price_list_items.price_list_id = ? THEN 1 WHEN price_list_items.price_list_id = ? THEN 2 WHEN price_list_items.price_list_id = ? THEN 3 WHEN price_list_items.price_list_id = ? THEN 4 ELSE 5 END ASC',
                    $orderedListIds
                ),
                default => $subquery->orderByRaw(
                    'CASE WHEN price_list_items.price_list_id = ? THEN 1 WHEN price_list_items.price_list_id = ? THEN 2 WHEN price_list_items.price_list_id = ? THEN 3 ELSE 4 END ASC',
                    array_slice($orderedListIds, 0, 3)
                ),
            };

            $subquery->limit(1);
        };
    }

    /**
     * @param  array{search?: ?string, category_id?: ?int, brand_id?: ?int, min_price?: float|int|string|null, max_price?: float|int|string|null, sort?: ?string}  $filters
     * @param  array<int, int>  $applicableListIds
     * @return Builder<Article>
     */
    private function buildQuery(array $filters, array $applicableListIds): Builder
    {
        $query = Article::query()
            ->active()
            ->where('is_online_publishable', true)
            ->whereHas('priceListItems', function (Builder $q) use ($applicableListIds): void {
                $q->whereIn('price_list_id', $applicableListIds);
            })
            ->whereHas('category', function (Builder $q): void {
                $q->where('is_active', true);
            })
            ->with(['category', 'brand', 'unitOfMeasure']);

        $query = $this->applyFilters($query, $filters, $applicableListIds);

        return $this->applySorting($query, $filters['sort'] ?? null, $applicableListIds);
    }

    private const NORMALIZED_DESCRIPTION_SQL = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(articles.description), 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u'), 'ü', 'u'), 'Á', 'a'), 'É', 'e'), 'Í', 'i'), 'Ó', 'o'), 'Ú', 'u'), 'Ü', 'u'), 'ñ', 'n'), 'Ñ', 'n')";

    /**
     * @param  Builder<Article>  $query
     * @param  array{search?: ?string, category_id?: ?int, brand_id?: ?int, min_price?: float|int|string|null, max_price?: float|int|string|null, sort?: ?string}  $filters
     * @param  array<int, int>  $applicableListIds
     * @return Builder<Article>
     */
    private function applyFilters(Builder $query, array $filters, array $applicableListIds): Builder
    {
        if (! empty($filters['search'])) {
            $search = Str::of((string) $filters['search'])
                ->trim()
                ->transliterate()
                ->lower()
                ->toString();

            $query->whereRaw(self::NORMALIZED_DESCRIPTION_SQL.' LIKE ?', ["%{$search}%"]);
        }

        if (! empty($filters['category_id'])) {
            $categoryId = (int) $filters['category_id'];

            // Include category and its direct children subcategories
            $categoryIds = Category::query()
                ->where('id', $categoryId)
                ->orWhere('parent_id', $categoryId)
                ->pluck('id')
                ->all();

            $query->whereIn('articles.category_id', $categoryIds);
        }

        if (! empty($filters['brand_id'])) {
            $query->where('articles.brand_id', (int) $filters['brand_id']);
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $minPrice = (float) $filters['min_price'];
            $query->where($this->getEffectivePriceSubqueryCallback($applicableListIds), '>=', $minPrice);
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $maxPrice = (float) $filters['max_price'];
            $query->where($this->getEffectivePriceSubqueryCallback($applicableListIds), '<=', $maxPrice);
        }

        return $query;
    }

    /**
     * @param  Builder<Article>  $query
     * @param  array<int, int>  $applicableListIds
     * @return Builder<Article>
     */
    private function applySorting(Builder $query, ?string $sort, array $applicableListIds): Builder
    {
        return match ($sort) {
            'price_asc' => $query
                ->orderBy($this->getEffectivePriceSubqueryCallback($applicableListIds), 'asc')
                ->orderBy('articles.description')
                ->orderBy('articles.id'),
            'price_desc' => $query
                ->orderBy($this->getEffectivePriceSubqueryCallback($applicableListIds), 'desc')
                ->orderBy('articles.description')
                ->orderBy('articles.id'),
            'name_asc' => $query
                ->orderBy('articles.description', 'asc')
                ->orderBy('articles.id'),
            'name_desc' => $query
                ->orderBy('articles.description', 'desc')
                ->orderBy('articles.id'),
            default => $query
                ->orderBy('articles.description')
                ->orderBy('articles.id'),
        };
    }
}
