<?php

namespace App\Actions\Ecommerce;

use App\Actions\Pricing\ResolveArticlePrice;
use App\Concerns\NormalizesStockQuantity;
use App\Enums\Catalog\ArticleStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Exceptions\Pricing\ArticleNotPricedException;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use Illuminate\Validation\ValidationException;

class AddArticleToCart
{
    use NormalizesStockQuantity;

    public function __construct(
        private readonly ResolveArticlePrice $resolveArticlePrice,
        private readonly GetOnlineAvailability $getOnlineAvailability,
    ) {}

    /**
     * Add an article to the customer's cart, accumulating quantity if already present.
     *
     * @throws ValidationException
     */
    public function execute(Customer $customer, Article $article, float|int|string $quantity): CartItem
    {
        $normalizedQuantity = $this->normalizeStockQuantity($quantity);
        $displayQuantity = rtrim(rtrim($normalizedQuantity, '0'), '.');

        if ($article->status !== ArticleStatus::Active) {
            throw ValidationException::withMessages([
                'article_id' => 'El artículo no se encuentra activo.',
            ]);
        }

        if (! $article->is_online_publishable) {
            throw ValidationException::withMessages([
                'article_id' => 'El artículo no está publicado para la venta online.',
            ]);
        }

        $article->loadMissing('unitOfMeasure');

        if (! $article->allowsDecimalQuantity() && bccomp($normalizedQuantity, bcadd($normalizedQuantity, '0', 0), 3) !== 0) {
            throw ValidationException::withMessages([
                'quantity' => 'La unidad de medida del artículo no admite cantidades decimales.',
            ]);
        }

        try {
            $this->resolveArticlePrice->execute($article, PriceListChannel::Online, $customer);
        } catch (ArticleNotPricedException) {
            throw ValidationException::withMessages([
                'article_id' => 'El artículo no posee un precio vigente para el canal online.',
            ]);
        }

        $availability = $this->getOnlineAvailability->forArticle($article);

        if (! $availability->isAvailable || bccomp($availability->availableQuantity, '0', 3) <= 0) {
            throw ValidationException::withMessages([
                'article_id' => 'El artículo no se encuentra disponible por falta de stock.',
            ]);
        }

        /** @var CartItem|null $existingItem */
        $existingItem = CartItem::query()
            ->where('customer_id', $customer->id)
            ->where('article_id', $article->id)
            ->first();

        if ($existingItem !== null) {
            $accumulated = bcadd($existingItem->quantity, $normalizedQuantity, 3);
            $displayAccumulated = rtrim(rtrim($accumulated, '0'), '.');

            if (bccomp($accumulated, $availability->availableQuantity, 3) > 0) {
                throw ValidationException::withMessages([
                    'quantity' => "La cantidad total solicitada ({$displayAccumulated}) supera el stock disponible en la tienda online ({$availability->availableQuantity}).",
                ]);
            }

            $existingItem->quantity = $accumulated;
            $existingItem->save();

            return $existingItem;
        }

        if (bccomp($normalizedQuantity, $availability->availableQuantity, 3) > 0) {
            throw ValidationException::withMessages([
                'quantity' => "La cantidad solicitada ({$displayQuantity}) supera el stock disponible en la tienda online ({$availability->availableQuantity}).",
            ]);
        }

        return CartItem::create([
            'customer_id' => $customer->id,
            'article_id' => $article->id,
            'quantity' => $normalizedQuantity,
        ]);
    }
}
