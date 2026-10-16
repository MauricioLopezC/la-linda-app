<?php

namespace App\Actions\Ecommerce;

use App\Concerns\NormalizesStockQuantity;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class UpdateCartItemQuantity
{
    use NormalizesStockQuantity;

    public function __construct(
        private readonly GetOnlineAvailability $getOnlineAvailability,
    ) {}

    /**
     * Update the quantity of an item in the customer's cart.
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function execute(Customer $customer, CartItem $cartItem, float|int|string $quantity): CartItem
    {
        if ($cartItem->customer_id !== $customer->id) {
            throw new AuthorizationException('No estás autorizado para modificar este artículo del carrito.');
        }

        $normalizedQuantity = $this->normalizeStockQuantity($quantity);
        $displayQuantity = rtrim(rtrim($normalizedQuantity, '0'), '.');

        $cartItem->loadMissing('article.unitOfMeasure');

        if (! $cartItem->article->allowsDecimalQuantity() && bccomp($normalizedQuantity, bcadd($normalizedQuantity, '0', 0), 3) !== 0) {
            throw ValidationException::withMessages([
                'quantity' => 'La unidad de medida del artículo no admite cantidades decimales.',
            ]);
        }

        $availability = $this->getOnlineAvailability->forArticle($cartItem->article);

        if (bccomp($normalizedQuantity, $availability->availableQuantity, 3) > 0) {
            throw ValidationException::withMessages([
                'quantity' => "La cantidad solicitada ({$displayQuantity}) supera el stock disponible en la tienda online ({$availability->availableQuantity}).",
            ]);
        }

        $cartItem->quantity = $normalizedQuantity;
        $cartItem->save();

        return $cartItem;
    }
}
