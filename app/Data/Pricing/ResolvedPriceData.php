<?php

namespace App\Data\Pricing;

use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use Spatie\LaravelData\Data;

/**
 * The resolved unit price for a sale line, together with the originating price list.
 *
 * Returned by ResolveArticlePrice so that the caller (sale or e-commerce) can persist
 * both the amount and a traceable reference to the list that supplied it.
 */
class ResolvedPriceData extends Data
{
    public function __construct(
        /** Decimal string, e.g. "1250.50" — matches the price_list_items.price precision. */
        public readonly string $unit_price,
        public readonly int $price_list_id,
        public readonly string $price_list_name,
        /** 'canal' | 'particular' */
        public readonly string $price_list_scope,
    ) {}

    public static function fromItem(PriceListItem $item): self
    {
        /** @var PriceList $list */
        $list = $item->priceList;

        return new self(
            unit_price: $item->price,
            price_list_id: $list->id,
            price_list_name: $list->name,
            price_list_scope: $list->scope->value,
        );
    }
}
