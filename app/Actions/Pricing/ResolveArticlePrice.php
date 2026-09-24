<?php

namespace App\Actions\Pricing;

use App\Data\Pricing\ResolvedPriceData;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Pricing\PriceListScope;
use App\Enums\Pricing\PriceListValidityStatus;
use App\Exceptions\Pricing\ArticleNotPricedException;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;

/**
 * Resolve the unit price for one sale line.
 *
 * Applies the strict cascade defined in HU-056:
 *   1. Customer's particular list (active + currently valid), if assigned.
 *   2. Channel list for the operation's channel (active + currently valid).
 *   3. General list (active + currently valid) — HU-011 guarantees one always exists.
 *
 * Only active and currently-valid lists are considered; future or expired lists are
 * silently skipped at each step, as if they did not exist.
 *
 * @throws ArticleNotPricedException when no applicable list holds a price for the article.
 */
class ResolveArticlePrice
{
    /**
     * @param  PriceListChannel::Mostrador|PriceListChannel::Online  $channel
     *
     * @throws ArticleNotPricedException
     */
    public function execute(
        Article $article,
        PriceListChannel $channel,
        ?Customer $customer = null,
    ): ResolvedPriceData {
        // Step 1 — customer's particular list.
        if ($customer !== null) {
            $item = $this->resolveFromParticular($article, $customer);

            if ($item !== null) {
                return ResolvedPriceData::fromItem($item);
            }
        }

        // Step 2 — channel list for the operation's channel.
        $item = $this->resolveFromChannel($article, $channel);

        if ($item !== null) {
            return ResolvedPriceData::fromItem($item);
        }

        // Step 3 — general fallback list.
        $item = $this->resolveFromGeneral($article);

        if ($item !== null) {
            return ResolvedPriceData::fromItem($item);
        }

        throw new ArticleNotPricedException($article);
    }

    /**
     * Look for a price in the customer's assigned particular list, if it is active and valid.
     */
    private function resolveFromParticular(Article $article, Customer $customer): ?PriceListItem
    {
        // Avoid an extra query if the relation was already eager-loaded.
        $customer->loadMissing('priceList');

        $priceList = $customer->priceList;

        if (
            $priceList === null
            || $priceList->scope !== PriceListScope::Particular
            || ! $priceList->is_active
            || $priceList->validityStatus() !== PriceListValidityStatus::Vigente
        ) {
            return null;
        }

        return PriceListItem::query()
            ->where('price_list_id', $priceList->id)
            ->where('article_id', $article->id)
            ->first();
    }

    /**
     * Look for a price in the active, currently-valid list for the given channel.
     */
    private function resolveFromChannel(Article $article, PriceListChannel $channel): ?PriceListItem
    {
        return $this->resolveFromList(
            $article,
            PriceList::query()
                ->active()
                ->currentlyValid()
                ->forChannel($channel)
                ->first()
        );
    }

    /**
     * Look for a price in the active, currently-valid general list.
     */
    private function resolveFromGeneral(Article $article): ?PriceListItem
    {
        return $this->resolveFromList(
            $article,
            PriceList::query()
                ->active()
                ->currentlyValid()
                ->forChannel(PriceListChannel::General)
                ->first()
        );
    }

    /**
     * Fetch the PriceListItem for the given article in $priceList, or null if the list is null.
     */
    private function resolveFromList(Article $article, ?PriceList $priceList): ?PriceListItem
    {
        if ($priceList === null) {
            return null;
        }

        /** @var PriceListItem|null $item */
        $item = PriceListItem::query()
            ->where('price_list_id', $priceList->id)
            ->where('article_id', $article->id)
            ->first();

        if ($item === null) {
            return null;
        }

        // Attach the already-loaded list so ResolvedPriceData::fromItem() can read it
        // without an extra query.
        $item->setRelation('priceList', $priceList);

        return $item;
    }
}
