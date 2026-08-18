<?php

namespace App\Data\Catalog;

use App\Models\Catalog\Category;
use Spatie\LaravelData\Data;

class CategoryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?int $parent_id,
        public bool $is_active,
    ) {}

    public static function fromModel(Category $category): self
    {
        return new self(
            id: $category->id,
            name: $category->name,
            parent_id: $category->parent_id,
            is_active: $category->is_active,
        );
    }
}
