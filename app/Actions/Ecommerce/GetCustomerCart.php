<?php

namespace App\Actions\Ecommerce;

use App\Actions\Pricing\ResolveArticlePrice;
use App\Data\Ecommerce\CartData;
use App\Data\Ecommerce\CartItemData;
use App\Enums\Catalog\ArticleStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Exceptions\Pricing\ArticleNotPricedException;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use Illuminate\Database\Eloquent\Collection;

class GetCustomerCart
{
    public function __construct(
        private readonly ResolveArticlePrice $resolveArticlePrice,
    ) {}

    /**
     * Retrieve the customer's cart with real-time price resolution and availability check.
     */
    public function execute(Customer $customer): CartData
    {
        /** @var Collection<int, CartItem> $cartItems */
        $cartItems = $customer->cartItems()
            ->with(['article.unitOfMeasure', 'article.category', 'article.brand'])
            ->orderBy('id')
            ->get();

        $itemsData = [];
        $totalAmount = 0.0;
        $totalQuantity = 0.0;
        $hasUnavailable = false;

        foreach ($cartItems as $item) {
            $article = $item->article;
            $isAvailable = true;
            $unavailableReason = null;
            $resolvedPrice = null;

            if ($article->status !== ArticleStatus::Active) {
                $isAvailable = false;
                $unavailableReason = 'Artículo inactivo';
            } elseif (! $article->is_online_publishable) {
                $isAvailable = false;
                $unavailableReason = 'No disponible para venta online';
            } else {
                try {
                    $resolvedPrice = $this->resolveArticlePrice->execute(
                        $article,
                        PriceListChannel::Online,
                        $customer,
                    );
                } catch (ArticleNotPricedException) {
                    $isAvailable = false;
                    $unavailableReason = 'Sin precio vigente';
                }
            }

            $unitPriceStr = $resolvedPrice?->unit_price;
            $subtotalStr = '0.00';
            $qty = (float) $item->quantity;
            $totalQuantity += $qty;

            if ($isAvailable && $unitPriceStr !== null) {
                $lineSubtotal = round($qty * (float) $unitPriceStr, 2);
                $subtotalStr = number_format($lineSubtotal, 2, '.', '');
                $totalAmount += $lineSubtotal;
            } else {
                $hasUnavailable = true;
            }

            $itemsData[] = CartItemData::fromModel(
                item: $item,
                unitPrice: $unitPriceStr,
                subtotal: $subtotalStr,
                isAvailable: $isAvailable,
                unavailableReason: $unavailableReason,
            );
        }

        return new CartData(
            items: $itemsData,
            total: number_format($totalAmount, 2, '.', ''),
            formatted_total: '$ '.number_format($totalAmount, 2, ',', '.'),
            lines_count: count($cartItems),
            total_quantity: number_format($totalQuantity, 3, '.', ''),
            has_unavailable_items: $hasUnavailable,
        );
    }
}
