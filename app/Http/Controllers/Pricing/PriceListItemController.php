<?php

namespace App\Http\Controllers\Pricing;

use App\Actions\Pricing\RemoveArticlePrice;
use App\Actions\Pricing\SetPriceListPrices;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\StorePriceListPricesRequest;
use App\Models\Catalog\Article;
use App\Models\Pricing\PriceList;
use Illuminate\Http\RedirectResponse;

class PriceListItemController extends Controller
{
    public function store(
        StorePriceListPricesRequest $request,
        PriceList $priceList,
        SetPriceListPrices $action,
    ): RedirectResponse {
        /** @var array<int, array{article_id: int|string, price: float|int|string}> $prices */
        $prices = $request->validated('prices');

        $saved = $action->handle($priceList, $prices);

        return back()->with('success', $saved === 1
            ? 'Precio guardado correctamente.'
            : "Se guardaron {$saved} precios correctamente.");
    }

    public function destroy(
        PriceList $priceList,
        Article $article,
        RemoveArticlePrice $action,
    ): RedirectResponse {
        $action->handle($priceList, $article);

        return back()->with('success', 'Precio quitado de la lista correctamente.');
    }
}
