<?php

namespace App\Actions\Sales;

use App\Actions\Pricing\ResolveArticlePrice;
use App\Exceptions\Pricing\ArticleNotPricedException;
use App\Models\Customers\Customer;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeSaleCustomer
{
    public function __construct(private ResolveArticlePrice $resolveArticlePrice) {}

    /**
     * Assign another customer to an open sale and re-price every line for that customer.
     *
     * All or nothing: if any article has no price for the new customer, the change is
     * rejected and the sale keeps its customer and prices, so it never mixes prices of two
     * customers.
     */
    public function handle(Sale $sale, Customer $customer): Sale
    {
        if (! $sale->isOpen()) {
            throw ValidationException::withMessages([
                'customer_id' => 'La venta ya no está abierta y no admite cambios.',
            ]);
        }

        if (! $customer->is_active) {
            throw ValidationException::withMessages([
                'customer_id' => 'El cliente seleccionado no está activo.',
            ]);
        }

        return DB::transaction(function () use ($sale, $customer): Sale {
            $sale->update(['customer_id' => $customer->id]);
            $sale->setRelation('customer', $customer);

            $sale->items()->with('article')->get()->each(function (SaleItem $item) use ($sale, $customer): void {
                try {
                    $resolvedPrice = $this->resolveArticlePrice->execute(
                        $item->article,
                        $sale->channel->toPriceListChannel(),
                        $customer,
                    );
                } catch (ArticleNotPricedException) {
                    throw ValidationException::withMessages([
                        'customer_id' => "El artículo \"{$item->article->description}\" no tiene precio para este cliente. No se cambió el cliente.",
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
