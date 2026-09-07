<?php

namespace App\Data\Purchasing;

use App\Models\Catalog\Article;
use Spatie\LaravelData\Data;

class PurchaseOrderArticleOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $internal_code,
        public string $description,
        public string $unit_of_measure,
    ) {}

    public static function fromModel(Article $article): self
    {
        return new self(
            id: $article->id,
            internal_code: $article->internal_code,
            description: $article->description,
            unit_of_measure: $article->unitOfMeasure->abbreviation ?? $article->unitOfMeasure->name ?? 'u',
        );
    }
}
