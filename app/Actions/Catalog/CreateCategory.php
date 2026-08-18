<?php

namespace App\Actions\Catalog;

use App\Models\Catalog\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCategory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Category
    {
        return DB::transaction(function () use ($data): Category {
            $parent = $this->resolveParent($data['parent_id'] ?? null);
            $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : true;

            if ($isActive && $parent !== null && ! $parent->is_active) {
                throw ValidationException::withMessages([
                    'parent_id' => 'No se puede crear una subcategoría activa dentro de una categoría inactiva.',
                ]);
            }

            return Category::create([
                'name' => (string) $data['name'],
                'parent_id' => $parent?->id,
                'is_active' => $isActive,
            ]);
        });
    }

    private function resolveParent(mixed $parentId): ?Category
    {
        if ($parentId === null) {
            return null;
        }

        $parent = Category::query()->findOrFail((int) $parentId);

        if (! $parent->isRoot()) {
            throw ValidationException::withMessages([
                'parent_id' => 'La categoría padre debe ser una categoría raíz.',
            ]);
        }

        return $parent;
    }
}
