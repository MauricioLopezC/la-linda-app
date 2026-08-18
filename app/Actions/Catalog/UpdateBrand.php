<?php

namespace App\Actions\Catalog;

use App\Models\Catalog\Brand;
use Illuminate\Validation\ValidationException;

class UpdateBrand
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Brand $brand, array $data): Brand
    {
        $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : $brand->is_active;

        if ($brand->is_active && ! $isActive && $brand->hasArticles()) {
            throw ValidationException::withMessages([
                'brand' => 'No se puede dar de baja una marca con artículos asociados.',
            ]);
        }

        $brand->update([
            'name' => (string) $data['name'],
            'is_active' => $isActive,
        ]);

        return $brand;
    }
}
