<?php

namespace App\Data\Catalog;

use App\Models\Catalog\Article;
use Spatie\LaravelData\Data;

class ArticleData extends Data
{
    public function __construct(
        public int $id,
        public string $description,
        public string $internal_code,
        public ?string $barcode,
        public int $category_id,
        public string $category_name,
        public ?int $brand_id,
        public ?string $brand_name,
        public int $unit_of_measure_id,
        public string $unit_of_measure_name,
        public string $status,
        public string $status_label,
        public bool $is_online_publishable,
    ) {}

    public static function fromModel(Article $article): self
    {
        return new self(
            id: $article->id,
            description: $article->description,
            internal_code: $article->internal_code,
            barcode: $article->barcode,
            category_id: $article->category_id,
            category_name: $article->category->name,
            brand_id: $article->brand_id,
            brand_name: $article->brand?->name,
            unit_of_measure_id: $article->unit_of_measure_id,
            unit_of_measure_name: $article->unitOfMeasure->name,
            status: $article->status->value,
            status_label: $article->status->label(),
            is_online_publishable: $article->is_online_publishable,
        );
    }
}
