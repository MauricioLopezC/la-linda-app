<?php

namespace App\Data\Sales;

use App\Models\Catalog\Article;
use Spatie\LaravelData\Data;

class SaleArticleOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $internal_code,
        public ?string $barcode,
        public string $description,
        public string $unit_of_measure,
    ) {}

    public static function fromModel(Article $article): self
    {
        return new self(
            id: $article->id,
            internal_code: $article->internal_code,
            barcode: $article->barcode,
            description: $article->description,
            unit_of_measure: $article->unitOfMeasure->abbreviation,
        );
    }
}
