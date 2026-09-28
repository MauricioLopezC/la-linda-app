<?php

namespace App\Actions\Pricing;

use App\Models\Pricing\VatRate;
use Illuminate\Validation\ValidationException;

class ToggleVatRateStatus
{
    public function handle(VatRate $vatRate): VatRate
    {
        if ($vatRate->is_active && $vatRate->isInUse()) {
            throw ValidationException::withMessages([
                'vat_rate' => 'No se puede desactivar una alícuota de IVA asignada a artículos u operaciones.',
            ]);
        }

        $vatRate->update([
            'is_active' => ! $vatRate->is_active,
        ]);

        return $vatRate;
    }
}
