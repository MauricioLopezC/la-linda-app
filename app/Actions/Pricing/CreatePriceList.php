<?php

namespace App\Actions\Pricing;

use App\Enums\Pricing\PriceListScope;
use App\Models\Pricing\PriceList;

class CreatePriceList
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): PriceList
    {
        $scope = PriceListScope::from((string) $data['scope']);

        return PriceList::create([
            'name' => (string) $data['name'],
            'description' => $data['description'] ?? null,
            'scope' => $scope,
            'channel' => $scope === PriceListScope::Canal ? $data['channel'] : null,
            'valid_from' => $data['valid_from'],
            'valid_to' => $data['valid_to'] ?? null,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
        ]);
    }
}
