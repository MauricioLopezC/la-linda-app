<?php

namespace App\Actions\Ecommerce;

use App\Models\Customers\Customer;

class ClearCart
{
    /**
     * Clear all items from the customer's cart.
     */
    public function execute(Customer $customer): void
    {
        $customer->cartItems()->delete();
    }
}
