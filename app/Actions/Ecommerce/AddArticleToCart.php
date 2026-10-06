<?php

namespace App\Actions\Ecommerce;

use App\Actions\Pricing\ResolveArticlePrice;
use App\Enums\Catalog\ArticleStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Exceptions\Pricing\ArticleNotPricedException;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use Illuminate\Validation\ValidationException;

class AddArticleToCart
{
    public function __construct(
        private readonly ResolveArticlePrice $resolveArticlePrice,
    ) {}

    /**
     * Add an article to the customer's cart, accumulating quantity if already present.
     *
     * @throws ValidationException
     */
    public function execute(Customer $customer, Article $article, float|int|string $quantity): CartItem
    {
        $numericQuantity = (float) $quantity;

        if ($numericQuantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad debe ser mayor a cero.',
            ]);
        }

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

        if (! $article->allowsDecimalQuantity() && fmod($numericQuantity, 1.0) !== 0.0) {
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

        /** @var CartItem|null $existingItem */
        $existingItem = CartItem::query()
            ->where('customer_id', $customer->id)
            ->where('article_id', $article->id)
            ->first();

        if ($existingItem !== null) {
            $accumulated = (float) $existingItem->quantity + $numericQuantity;
            $existingItem->quantity = number_format($accumulated, 3, '.', '');
            $existingItem->save();

            return $existingItem;
        }

        return CartItem::create([
            'customer_id' => $customer->id,
            'article_id' => $article->id,
            'quantity' => number_format($numericQuantity, 3, '.', ''),
        ]);
    }
}
