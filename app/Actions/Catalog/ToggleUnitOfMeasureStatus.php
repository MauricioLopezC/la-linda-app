<?php

namespace App\Actions\Catalog;

use App\Models\Catalog\UnitOfMeasure;
use Illuminate\Validation\ValidationException;

class ToggleUnitOfMeasureStatus
{
    public function handle(UnitOfMeasure $unitOfMeasure): UnitOfMeasure
    {
        if ($unitOfMeasure->is_active && $unitOfMeasure->hasArticles()) {
            throw ValidationException::withMessages([
                'unit_of_measure' => 'No se puede dar de baja una unidad de medida con artículos asociados.',
            ]);
        }

        $unitOfMeasure->update([
            'is_active' => ! $unitOfMeasure->is_active,
        ]);

        return $unitOfMeasure;
    }
}
