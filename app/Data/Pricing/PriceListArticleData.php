<?php

namespace App\Data\Pricing;

use App\Enums\Catalog\ArticleStatus;
use App\Models\Catalog\Article;
use Spatie\LaravelData\Data;

/**
 * One row of the price loading screen: a catalog article and the price it holds in the list being
 * edited, which is null when the article has no price there yet.
 */
class PriceListArticleData extends Data
{
    public function __construct(
        public int $id,
        public string $internal_code,
        public string $description,
        public string $category_name,
        public ?string $brand_name,
        public string $unit_of_measure_name,
        public string $status,
        public string $status_label,
        public bool $is_active,
        public ?string $price,
        public bool $has_price,
    ) {}

    public static function fromModel(Article $article): self
    {
        /** @var string|float|null $price */
        $price = $article->getAttribute('price_list_item_price');

        return new self(
            id: $article->id,
            internal_code: $article->internal_code,
            description: $article->description,
            category_name: $article->category->name,
            brand_name: $article->brand?->name,
            unit_of_measure_name: $article->unitOfMeasure->name,
            status: $article->status->value,
            status_label: $article->status->label(),
            is_active: $article->status === ArticleStatus::Active,
            price: $price === null ? null : number_format((float) $price, 2, '.', ''),
            has_price: $article->getAttribute('price_list_item_id') !== null,
        );
    }
}
