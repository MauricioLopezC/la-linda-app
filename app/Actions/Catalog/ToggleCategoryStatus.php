<?php

namespace App\Actions\Catalog;

use App\Models\Catalog\Category;
use Illuminate\Validation\ValidationException;

class ToggleCategoryStatus
{
    public function handle(Category $category): Category
    {
        if ($category->is_active) {
            if ($category->children()->active()->exists()) {
                throw ValidationException::withMessages([
                    'category' => 'No se puede dar de baja una categoría con subcategorías activas.',
                ]);
            }

            if ($category->hasArticles()) {
                throw ValidationException::withMessages([
                    'category' => 'No se puede dar de baja una categoría con artículos asociados.',
                ]);
            }
        }

        if (! $category->is_active && $category->parent !== null && ! $category->parent->is_active) {
            throw ValidationException::withMessages([
                'category' => 'No se puede activar una subcategoría cuyo padre está inactivo.',
            ]);
        }

        $category->update([
            'is_active' => ! $category->is_active,
        ]);

        return $category;
    }
}
