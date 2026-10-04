<?php

namespace App\Http\Controllers\Ecommerce;

use App\Actions\Ecommerce\ConsultOnlineCatalog;
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
        ];

        $articles = $action->execute($filters, $customer, perPage: 16);
        $categories = Category::query()
            ->active()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return Inertia::render('ecommerce/index', [
            'articles' => $articles,
            'categories' => CategoryData::collect($categories),
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'category_id' => $filters['category_id'] ? (string) $filters['category_id'] : 'all',
            ],
        ]);
    }
}
