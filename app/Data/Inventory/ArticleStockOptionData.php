<?php

namespace App\Data\Inventory;

use App\Models\Catalog\Article;
use Spatie\LaravelData\Data;

class ArticleStockOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $description,
        public string $internal_code,
        public ?string $barcode,
        public string $category_name,
        public ?string $brand_name,
        public string $unit_of_measure_name,
        public string $unit_of_measure_abbreviation,
        public bool $allows_decimals = true,
    ) {}

    /**
     * Build the option from an article with its category, brand and unit of measure loaded.
     */
    public static function fromModel(Article $article): self
    {
        return new self(
            id: $article->id,
            description: $article->description,
            internal_code: $article->internal_code,
            barcode: $article->barcode,
            category_name: $article->category->name,
            brand_name: $article->brand?->name,
            unit_of_measure_name: $article->unitOfMeasure->name,
            unit_of_measure_abbreviation: $article->unitOfMeasure->abbreviation,
            allows_decimals: $article->allowsDecimalQuantity(),
        );
    }
}
