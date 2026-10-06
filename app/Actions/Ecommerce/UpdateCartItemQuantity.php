<?php

namespace App\Actions\Ecommerce;

use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class UpdateCartItemQuantity
{
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

        $numericQuantity = (float) $quantity;

        if ($numericQuantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad debe ser mayor a cero.',
            ]);
        }

        $cartItem->loadMissing('article.unitOfMeasure');

        if (! $cartItem->article->allowsDecimalQuantity() && fmod($numericQuantity, 1.0) !== 0.0) {
            throw ValidationException::withMessages([
                'quantity' => 'La unidad de medida del artículo no admite cantidades decimales.',
            ]);
        }

        $cartItem->quantity = number_format($numericQuantity, 3, '.', '');
        $cartItem->save();

        return $cartItem;
    }
}
