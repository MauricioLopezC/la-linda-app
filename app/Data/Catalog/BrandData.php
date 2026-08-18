<?php

namespace App\Data\Catalog;

use App\Models\Catalog\Brand;
use Spatie\LaravelData\Data;

class BrandData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $is_active,
    ) {}

    public static function fromModel(Brand $brand): self
    {
        return new self(
            id: $brand->id,
            name: $brand->name,
            is_active: $brand->is_active,
        );
    }
}
