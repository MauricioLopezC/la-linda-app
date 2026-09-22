<?php

namespace App\Http\Controllers\Pricing;

use App\Actions\Pricing\CreatePriceList;
use App\Actions\Pricing\TogglePriceListStatus;
use App\Actions\Pricing\UpdatePriceList;
use App\Data\Pricing\PriceListData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\StorePriceListRequest;
use App\Http\Requests\Pricing\UpdatePriceListRequest;
use App\Models\Pricing\PriceList;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PriceListController extends Controller
{
    public function index(): Response
    {
        $priceLists = PriceList::query()->orderBy('name')->get();

        return Inertia::render('pricing/price-lists/index', [
            'priceLists' => PriceListData::collect($priceLists),
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
