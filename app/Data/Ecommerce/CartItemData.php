<?php

namespace App\Data\Ecommerce;

use App\Models\Ecommerce\CartItem;
use Spatie\LaravelData\Data;

class CartItemData extends Data
{
    public function __construct(
        public int $id,
        public int $article_id,
        public string $article_description,
        public string $article_internal_code,
        public ?string $article_barcode,
        public ?string $article_image_url,
        public ?string $brand_name,
        public string $category_name,
        public string $unit_of_measure_name,
        public string $unit_of_measure_abbreviation,
        public bool $allows_decimals,
        public string $quantity,
        public ?string $unit_price,
        public ?string $formatted_unit_price,
        public string $subtotal,
        public string $formatted_subtotal,
        public bool $is_available,
        public ?string $unavailable_reason,
    ) {}

    public static function fromModel(
        CartItem $item,
        ?string $unitPrice,
        string $subtotal,
        bool $isAvailable,
        ?string $unavailableReason,
    ): self {
        $article = $item->article;
        $formattedUnitPrice = $unitPrice !== null
            ? '$ '.number_format((float) $unitPrice, 2, ',', '.')
            : null;
        $formattedSubtotal = '$ '.number_format((float) $subtotal, 2, ',', '.');

        return new self(
            id: $item->id,
            article_id: $article->id,
            article_description: $article->description,
            article_internal_code: $article->internal_code,
            article_barcode: $article->barcode,
            article_image_url: $article->image_url,
            brand_name: $article->brand?->name,
            category_name: $article->category->name,
            unit_of_measure_name: $article->unitOfMeasure->name,
            unit_of_measure_abbreviation: $article->unitOfMeasure->abbreviation,
            allows_decimals: $article->allowsDecimalQuantity(),
            quantity: (string) $item->quantity,
            unit_price: $unitPrice,
            formatted_unit_price: $formattedUnitPrice,
            subtotal: $subtotal,
            formatted_subtotal: $formattedSubtotal,
            is_available: $isAvailable,
            unavailable_reason: $unavailableReason,
        );
    }
}
