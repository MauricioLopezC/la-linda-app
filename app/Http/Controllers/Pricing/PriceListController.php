<?php

namespace App\Http\Controllers\Pricing;

use App\Actions\Pricing\ConsultPriceListArticles;
use App\Actions\Pricing\CreatePriceList;
use App\Actions\Pricing\TogglePriceListStatus;
use App\Actions\Pricing\UpdatePriceList;
use App\Data\Catalog\CategoryData;
use App\Data\Pricing\PriceListArticleData;
use App\Data\Pricing\PriceListData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\StorePriceListRequest;
use App\Http\Requests\Pricing\UpdatePriceListRequest;
use App\Models\Catalog\Category;
use App\Models\Pricing\PriceList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PriceListController extends Controller
{
    public function index(): Response
    {
        $priceLists = PriceList::query()->withCount('items')->orderBy('name')->get();

        return Inertia::render('pricing/price-lists/index', [
            'priceLists' => PriceListData::collect($priceLists),
        ]);
    }

    /**
     * Display the price loading screen of a single list (HU-012).
     */
    public function show(Request $request, PriceList $priceList, ConsultPriceListArticles $consultAction): Response
    {
        $filters = [
            'search' => $request->query('search'),
            'category_id' => $request->filled('category_id') ? (int) $request->query('category_id') : null,
            'price_status' => $request->query('price_status', 'all'),
        ];

        $articles = $consultAction->execute($priceList, $filters);

        return Inertia::render('pricing/price-lists/show', [
            'priceList' => PriceListData::fromModel($priceList),
            'articles' => PriceListArticleData::collect($articles),
            'categories' => CategoryData::collect(Category::query()->active()->orderBy('name')->get()),
            'filters' => $filters,
        ]);
    }

    public function store(StorePriceListRequest $request, CreatePriceList $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Lista de precios creada correctamente.');
    }

    public function update(UpdatePriceListRequest $request, PriceList $priceList, UpdatePriceList $action): RedirectResponse
    {
        $action->handle($priceList, $request->validated());

        return back()->with('success', 'Lista de precios actualizada correctamente.');
    }

    public function toggleStatus(PriceList $priceList, TogglePriceListStatus $action): RedirectResponse
    {
        $action->handle($priceList);

        return back()->with('success', 'Estado de la lista de precios actualizado.');
    }
}
