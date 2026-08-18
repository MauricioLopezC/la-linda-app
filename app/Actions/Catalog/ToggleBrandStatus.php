<?php

namespace App\Actions\Catalog;

use App\Models\Catalog\Brand;
use Illuminate\Validation\ValidationException;

class ToggleBrandStatus
{
    public function handle(Brand $brand): Brand
    {
        if ($brand->is_active && $brand->hasArticles()) {
            throw ValidationException::withMessages([
                'brand' => 'No se puede dar de baja una marca con artículos asociados.',
            ]);
        }

        $brand->update([
            'is_active' => ! $brand->is_active,
        ]);

        return $brand;
    }
}
