<?php

namespace App\Data\Ecommerce;

use App\Data\Pricing\ResolvedPriceData;
use App\Enums\Pricing\PriceListScope;
use App\Models\Catalog\Article;
use Spatie\LaravelData\Data;

class OnlineCatalogArticleData extends Data
{
    public function __construct(
        public int $id,
        public string $description,
        public string $internal_code,
        public ?string $barcode,
        public ?string $image_url,
        public ?string $brand_name,
        public string $unit_of_measure_name,
        public string $unit_of_measure_abbreviation,
        public int $category_id,
        public string $category_name,
        public string $price,
        public string $formatted_price,
        public string $price_list_name,
        public string $price_list_scope,
        public bool $is_particular_price,
        public bool $allows_decimals,
    ) {}

    public static function fromArticleAndPrice(Article $article, ResolvedPriceData $priceData): self
    {
        return new self(
            id: $article->id,
            description: $article->description,
            internal_code: $article->internal_code,
            barcode: $article->barcode,
            image_url: $article->image_url,
            brand_name: $article->brand?->name,
            unit_of_measure_name: $article->unitOfMeasure->name,
            unit_of_measure_abbreviation: $article->unitOfMeasure->abbreviation,
            category_id: $article->category_id,
            category_name: $article->category->name,
            price: $priceData->unit_price,
            formatted_price: '$ '.number_format((float) $priceData->unit_price, 2, ',', '.'),
            price_list_name: $priceData->price_list_name,
            price_list_scope: $priceData->price_list_scope,
            is_particular_price: $priceData->price_list_scope === PriceListScope::Particular->value,
            allows_decimals: $article->allowsDecimalQuantity(),
        );
    }
}
