<?php

namespace App\Actions\Sales;

use App\Actions\Pricing\ResolveArticlePrice;
use App\Enums\Pricing\PriceListValidityStatus;
use App\Exceptions\Pricing\ArticleNotPricedException;
use App\Models\Pricing\PriceList;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeSalePriceList
{
    public function __construct(private ResolveArticlePrice $resolveArticlePrice) {}

    /**
     * Assign a specific price list (or reset to null/automatic) to an open sale and re-price every line.
     *
     * All or nothing: if any article has no price with the selected list, the change is
     * rejected and the sale keeps its previous list and prices, so it never mixes lists.
     */
    public function handle(Sale $sale, ?PriceList $priceList): Sale
    {
        if (! $sale->acceptsChanges()) {
            throw ValidationException::withMessages([
                'price_list_id' => 'La venta no admite cambios porque está cerrada o su turno de caja no está abierto.',
            ]);
        }

        if ($priceList !== null) {
            if (! $priceList->is_active || $priceList->validityStatus() !== PriceListValidityStatus::Vigente) {
                throw ValidationException::withMessages([
                    'price_list_id' => 'La lista de precios seleccionada no está activa o vigente.',
                ]);
            }
        }

        return DB::transaction(function () use ($sale, $priceList): Sale {
            $sale->update(['price_list_id' => $priceList?->id]);
            $sale->setRelation('priceList', $priceList);

            $sale->items()->with('article')->get()->each(function (SaleItem $item) use ($sale, $priceList): void {
                try {
                    $resolvedPrice = $this->resolveArticlePrice->execute(
                        $item->article,
                        $sale->channel->toPriceListChannel(),
                        $sale->customer,
                        $priceList,
                    );
                } catch (ArticleNotPricedException) {
                    throw ValidationException::withMessages([
                        'price_list_id' => "El artículo \"{$item->article->description}\" no tiene precio con la lista seleccionada. No se cambió la lista de precios.",
                    ]);
                }

                $item->update([
                    'unit_price' => $resolvedPrice->unit_price,
                    'price_list_id' => $resolvedPrice->price_list_id,
                    'line_total' => SaleItem::calculateLineTotal($item->quantity, $resolvedPrice->unit_price),
                ]);
            });

            $sale->recalculateTotal();

            return $sale;
        });
    }
}
