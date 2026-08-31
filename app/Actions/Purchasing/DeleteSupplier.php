<?php

namespace App\Actions\Purchasing;

use App\Models\Purchasing\Supplier;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DeleteSupplier
{
    public function handle(Supplier $supplier): void
    {
        if ($supplier->hasAssociatedRecords()) {
            throw ValidationException::withMessages([
                'supplier' => 'No se puede eliminar físicamente un proveedor que posee comprobantes u operaciones registradas. Realizá la baja lógica desactivándolo.',
            ]);
        }

        $id = $supplier->id;
        $name = $supplier->business_name;
        $taxId = $supplier->tax_id;

        $supplier->delete();

        Log::info(sprintf(
            'Supplier physically deleted [ID: %d, Business Name: %s, CUIT: %s] by User ID: %s',
            $id,
            $name,
            $taxId,
            auth()->id() ?? 'system'
        ));
    }
}
