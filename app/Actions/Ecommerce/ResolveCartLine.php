<?php

namespace App\Actions\Ecommerce;

use App\Actions\Pricing\ResolveArticlePrice;
use App\Data\Pricing\ResolvedPriceData;
use App\Enums\Catalog\ArticleStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Exceptions\Pricing\ArticleNotPricedException;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;

/**
 * Decide whether a cart article can be bought online right now, and at which price.
 *
 * Shared by the cart (EPIC-13), which shows the current price, and by the order placement
 * (HU-062), which freezes it, so both apply exactly the same availability rules.
 */
class ResolveCartLine
{
    public function __construct(
        private readonly ResolveArticlePrice $resolveArticlePrice,
    ) {}

    /**
     * @return array{price: ResolvedPriceData|null, unavailable_reason: string|null}
     */
    public function execute(Article $article, Customer $customer): array
    {
        if ($article->status !== ArticleStatus::Active) {
            return ['price' => null, 'unavailable_reason' => 'Artículo inactivo'];
        }

        if (! $article->is_online_publishable) {
            return ['price' => null, 'unavailable_reason' => 'No disponible para venta online'];
        }

        try {
            $price = $this->resolveArticlePrice->execute($article, PriceListChannel::Online, $customer);
        } catch (ArticleNotPricedException) {
            return ['price' => null, 'unavailable_reason' => 'Sin precio vigente'];
        }

        return ['price' => $price, 'unavailable_reason' => null];
    }
}
