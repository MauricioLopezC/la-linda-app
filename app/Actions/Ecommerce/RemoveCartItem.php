<?php

namespace App\Actions\Ecommerce;

use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use Illuminate\Auth\Access\AuthorizationException;

class RemoveCartItem
{
    /**
     * Remove an item from the customer's cart.
     *
     * @throws AuthorizationException
     */
    public function execute(Customer $customer, CartItem $cartItem): void
    {
        if ($cartItem->customer_id !== $customer->id) {
            throw new AuthorizationException('No estás autorizado para eliminar este artículo del carrito.');
        }

        $cartItem->delete();
    }
}
