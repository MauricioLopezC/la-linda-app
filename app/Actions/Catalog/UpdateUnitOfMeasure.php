<?php

namespace App\Actions\Catalog;

use App\Models\Catalog\UnitOfMeasure;
use Illuminate\Validation\ValidationException;

class UpdateUnitOfMeasure
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(UnitOfMeasure $unitOfMeasure, array $data): UnitOfMeasure
    {
        $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : $unitOfMeasure->is_active;

        if ($unitOfMeasure->is_active && ! $isActive && $unitOfMeasure->hasArticles()) {
            throw ValidationException::withMessages([
                'unit_of_measure' => 'No se puede dar de baja una unidad de medida con artículos asociados.',
            ]);
        }

        $unitOfMeasure->update([
            'name' => (string) $data['name'],
            'abbreviation' => (string) $data['abbreviation'],
            'is_active' => $isActive,
        ]);

        return $unitOfMeasure;
    }
}
