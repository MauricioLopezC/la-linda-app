<?php

namespace App\Actions\Customers;

use App\Models\Customers\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ToggleCustomerStatus
{
    public function handle(Customer $customer): Customer
    {
        if ($customer->isProtected()) {
            throw ValidationException::withMessages([
                'customer' => 'El cliente por defecto Consumidor Final no puede ser desactivado.',
            ]);
        }

        $customer->update([
            'is_active' => ! $customer->is_active,
        ]);

        Log::info(sprintf(
            'Customer status toggled [ID: %d, Name: %s, is_active: %s] by User ID: %s',
            $customer->id,
            $customer->name,
            $customer->is_active ? 'active' : 'inactive',
            auth()->id() ?? 'system'
        ));

        return $customer;
    }
}
