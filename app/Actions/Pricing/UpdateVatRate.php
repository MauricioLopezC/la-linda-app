<?php

namespace App\Actions\Pricing;

use App\Models\Pricing\VatRate;
use Illuminate\Validation\ValidationException;

class UpdateVatRate
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(VatRate $vatRate, array $data): VatRate
    {
        $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : $vatRate->is_active;

        if ($vatRate->is_active && ! $isActive && $vatRate->isInUse()) {
            throw ValidationException::withMessages([
                'vat_rate' => 'No se puede dar de baja una alícuota de IVA que ya ha sido utilizada en operaciones registradas.',
            ]);
        }

        $vatRate->update([
            'description' => (string) $data['description'],
            'percentage' => (float) $data['percentage'],
            'is_active' => $isActive,
        ]);

        return $vatRate;
    }
}
