<?php

namespace App\Actions\Ecommerce;

use App\Data\Ecommerce\CartData;
use App\Data\Ecommerce\CartItemData;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use Illuminate\Database\Eloquent\Collection;

class GetCustomerCart
{
    public function __construct(
        private readonly ResolveCartLine $resolveCartLine,
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
            $line = $this->resolveCartLine->execute($item->article, $customer);
            $resolvedPrice = $line['price'];
            $unavailableReason = $line['unavailable_reason'];
            $isAvailable = $resolvedPrice !== null;

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
