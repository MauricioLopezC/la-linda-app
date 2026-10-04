<?php

namespace App\Actions\Ecommerce;

use App\Actions\Pricing\ResolveArticlePrice;
use App\Data\Ecommerce\OnlineCatalogArticleData;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Pricing\PriceListScope;
use App\Enums\Pricing\PriceListValidityStatus;
use App\Models\Catalog\Article;
use App\Models\Catalog\Category;
use App\Models\Customers\Customer;
use App\Models\Pricing\PriceList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ConsultOnlineCatalog
{
    public function __construct(
        private readonly ResolveArticlePrice $resolveArticlePrice,
    ) {}

    /**
     * @param  array{search?: ?string, category_id?: ?int}  $filters
     * @return LengthAwarePaginator<int, OnlineCatalogArticleData>
     */
    public function execute(
        array $filters = [],
        ?Customer $customer = null,
        int $perPage = 16,
    ): LengthAwarePaginator {
        $applicableListIds = $this->resolveApplicablePriceListIds($customer);

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
    private function resolveApplicablePriceListIds(?Customer $customer): array
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
     * @param  array{search?: ?string, category_id?: ?int}  $filters
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

        return $this->applyFilters($query, $filters)
            ->orderBy('articles.description')
            ->orderBy('articles.id');
    }

    /**
     * @param  Builder<Article>  $query
     * @param  array{search?: ?string, category_id?: ?int}  $filters
     * @return Builder<Article>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = mb_strtolower(trim((string) $filters['search']));
            $query->whereRaw('LOWER(articles.description) LIKE ?', ["%{$search}%"]);
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

        return $query;
    }
}
