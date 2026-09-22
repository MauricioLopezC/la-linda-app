<?php

namespace App\Actions\Pricing;

use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use Illuminate\Support\Facades\DB;

/**
 * Set (create or update) the price of one or more articles inside a price list.
 *
 * The screen saves every row the user edited in one go, so a single Action covers both the first
 * price of an article and a later correction: the unique (price_list_id, article_id) index makes
 * the upsert idempotent.
 */
class SetPriceListPrices
{
    /**
     * @param  array<int, array{article_id: int|string, price: float|int|string}>  $prices
     * @return int the number of articles whose price was set
     */
    public function handle(PriceList $priceList, array $prices): int
    {
        if ($prices === []) {
            return 0;
        }

        $now = now();

        $rows = array_map(fn (array $price): array => [
            'price_list_id' => $priceList->id,
            'article_id' => (int) $price['article_id'],
            'price' => round((float) $price['price'], 2),
            'created_at' => $now,
            'updated_at' => $now,
        ], $prices);

        DB::transaction(function () use ($rows): void {
            PriceListItem::query()->upsert(
                $rows,
                ['price_list_id', 'article_id'],
                ['price', 'updated_at'],
            );
        });

        return count($rows);
    }
}
