<?php

namespace App\Actions\Pricing;

use App\Models\Pricing\VatRate;

class CreateVatRate
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): VatRate
    {
        return VatRate::create([
            'description' => (string) $data['description'],
            'percentage' => (float) $data['percentage'],
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
        ]);
    }
}
