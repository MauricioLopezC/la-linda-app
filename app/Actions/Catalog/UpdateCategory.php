<?php

namespace App\Actions\Catalog;

use App\Models\Catalog\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateCategory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data): Category {
            $parent = $this->resolveParent($category, $data['parent_id'] ?? null);
            $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : $category->is_active;

            if ($parent !== null && $category->children()->exists()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Una categoría con subcategorías no puede convertirse en subcategoría.',
                ]);
            }

            if ($isActive && $parent !== null && ! $parent->is_active) {
                throw ValidationException::withMessages([
                    'parent_id' => 'No se puede activar una subcategoría cuyo padre está inactivo.',
                ]);
            }

            if ($category->is_active && ! $isActive) {
                $this->ensureCanDeactivate($category);
            }

            $category->update([
                'name' => (string) $data['name'],
                'parent_id' => $parent?->id,
                'is_active' => $isActive,
            ]);

            return $category;
        });
    }

    private function resolveParent(Category $category, mixed $parentId): ?Category
    {
        if ($parentId === null) {
            return null;
        }

        if ((int) $parentId === $category->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Una categoría no puede ser padre de sí misma.',
            ]);
        }

        $parent = Category::query()->findOrFail((int) $parentId);

        if (! $parent->isRoot()) {
            throw ValidationException::withMessages([
                'parent_id' => 'La categoría padre debe ser una categoría raíz.',
            ]);
        }

        return $parent;
    }

    private function ensureCanDeactivate(Category $category): void
    {
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
}
