<?php

namespace App\Data\Catalog;

use App\Models\Catalog\UnitOfMeasure;
use Spatie\LaravelData\Data;

class UnitOfMeasureData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $abbreviation,
        public bool $is_active,
    ) {}

    public static function fromModel(UnitOfMeasure $unitOfMeasure): self
    {
        return new self(
            id: $unitOfMeasure->id,
            name: $unitOfMeasure->name,
            abbreviation: $unitOfMeasure->abbreviation,
            is_active: $unitOfMeasure->is_active,
        );
    }
}
