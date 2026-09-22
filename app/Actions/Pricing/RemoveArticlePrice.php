<?php

namespace App\Actions\Pricing;

use App\Models\Catalog\Article;
use App\Models\Pricing\PriceList;

/**
 * Drop an article's price from a price list, sending it back to the "without a price" filter.
 */
class RemoveArticlePrice
{
    public function handle(PriceList $priceList, Article $article): void
    {
        $priceList->items()
            ->where('article_id', $article->id)
            ->delete();
    }
}
