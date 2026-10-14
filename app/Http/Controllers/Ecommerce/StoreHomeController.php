<?php

namespace App\Http\Controllers\Ecommerce;

use App\Actions\Ecommerce\ConsultOnlineCatalog;
use App\Data\Catalog\BrandData;
use App\Data\Catalog\CategoryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ecommerce\ConsultOnlineCatalogRequest;
use App\Models\Catalog\Category;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class StoreHomeController extends Controller
{
    /**
     * Display the online store home page and catalog.
     */
    public function index(
        ConsultOnlineCatalogRequest $request,
        ConsultOnlineCatalog $action,
    ): Response {
        /** @var User|null $user */
        $user = $request->user();
        $customer = $user?->customer;

        $filters = [
            'search' => $request->query('search'),
            'category_id' => $request->filled('category_id') ? (int) $request->query('category_id') : null,
            'brand_id' => $request->filled('brand_id') ? (int) $request->query('brand_id') : null,
            'min_price' => $request->filled('min_price') ? $request->query('min_price') : null,
            'max_price' => $request->filled('max_price') ? $request->query('max_price') : null,
            'sort' => (string) ($request->query('sort') ?: 'relevance'),
        ];

        $articles = $action->execute($filters, $customer, perPage: 16);

        $applicableListIds = $action->resolveApplicablePriceListIds($customer);
        $brands = $action->getAvailableBrands($applicableListIds);

        $categories = Category::query()
            ->active()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        $hasActiveFilter = ! empty($filters['search'])
            || ! empty($filters['category_id'])
            || ! empty($filters['brand_id'])
            || $filters['min_price'] !== null
            || $filters['max_price'] !== null;

        $suggestedArticles = [];
        if ($articles->isEmpty() && $hasActiveFilter) {
            $suggestedArticles = $action->execute([], $customer, perPage: 8)->items();
        }

        return Inertia::render('ecommerce/index', [
            'articles' => $articles,
            'suggestedArticles' => $suggestedArticles,
            'categories' => CategoryData::collect($categories),
            'brands' => BrandData::collect($brands),
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'category_id' => $filters['category_id'] ? (string) $filters['category_id'] : 'all',
                'brand_id' => $filters['brand_id'] ? (string) $filters['brand_id'] : 'all',
                'min_price' => $filters['min_price'] !== null ? (string) $filters['min_price'] : '',
                'sort' => $filters['sort'],
            ],
        ]);
    }
}
