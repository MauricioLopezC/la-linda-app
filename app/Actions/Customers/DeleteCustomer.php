<?php

namespace App\Actions\Customers;

use App\Models\Customers\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DeleteCustomer
{
    public function handle(Customer $customer): void
    {
        if ($customer->isProtected()) {
            throw ValidationException::withMessages([
                'customer' => 'El cliente por defecto Consumidor Final no puede ser eliminado.',
            ]);
        }

        if ($customer->hasAssociatedRecords()) {
            throw ValidationException::withMessages([
                'customer' => 'No se puede eliminar físicamente un cliente que posee operaciones registradas. Realizá la baja lógica desactivándolo.',
            ]);
        }

        $id = $customer->id;
        $name = $customer->name;
        $idNumber = $customer->id_number;

        $customer->delete();

        Log::info(sprintf(
            'Customer physically deleted [ID: %d, Name: %s, ID Number: %s] by User ID: %s',
            $id,
            $name,
            $idNumber ?? 'N/A',
            auth()->id() ?? 'system'
        ));
    }
}
