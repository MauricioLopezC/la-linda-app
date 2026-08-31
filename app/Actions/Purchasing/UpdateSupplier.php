<?php

namespace App\Actions\Purchasing;

use App\Models\Purchasing\Supplier;
use App\Rules\Purchasing\ValidCuit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UpdateSupplier
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Supplier $supplier, array $data): Supplier
    {
        return DB::transaction(function () use ($supplier, $data): Supplier {
            $newTaxId = ValidCuit::sanitize((string) $data['tax_id']);

            if ($supplier->tax_id !== $newTaxId && $supplier->hasAssociatedRecords()) {
                throw ValidationException::withMessages([
                    'tax_id' => 'No se puede modificar el CUIT de un proveedor que ya posee comprobantes u órdenes registradas.',
                ]);
            }

            $supplier->update([
                'business_name' => (string) $data['business_name'],
                'tax_id' => $newTaxId,
                'tax_condition' => $data['tax_condition'],
                'address' => isset($data['address']) && $data['address'] !== '' ? (string) $data['address'] : null,
                'rubro' => isset($data['rubro']) && $data['rubro'] !== '' ? (string) $data['rubro'] : null,
                'bank_account' => isset($data['bank_account']) && $data['bank_account'] !== '' ? (string) $data['bank_account'] : null,
                'commercial_terms' => isset($data['commercial_terms']) && $data['commercial_terms'] !== '' ? (string) $data['commercial_terms'] : null,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $supplier->is_active,
            ]);

            Log::info(sprintf(
                'Supplier updated [ID: %d, Business Name: %s, CUIT: %s] by User ID: %s',
                $supplier->id,
                $supplier->business_name,
                $supplier->tax_id,
                auth()->id() ?? 'system'
            ));

            return $supplier;
        });
    }
}
