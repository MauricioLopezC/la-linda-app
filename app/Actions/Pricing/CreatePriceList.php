<?php

namespace App\Actions\Pricing;

use App\Models\Pricing\PriceList;

class CreatePriceList
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): PriceList
    {
        return PriceList::create([
            'name' => (string) $data['name'],
            'description' => $data['description'] ?? null,
            'channel' => $data['channel'],
            'valid_from' => $data['valid_from'],
            'valid_to' => $data['valid_to'] ?? null,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
        ]);
    }
}
