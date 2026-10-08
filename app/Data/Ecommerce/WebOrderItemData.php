<?php

namespace App\Data\Ecommerce;

use App\Models\Ecommerce\WebOrderItem;
use Spatie\LaravelData\Data;

/**
 * One line of an online order, with the price frozen when it was placed.
 */
class WebOrderItemData extends Data
{
    public function __construct(
        public int $id,
        public int $article_id,
        public string $article_description,
        public string $article_internal_code,
        public ?string $article_image_url,
        public string $unit_of_measure_abbreviation,
        public string $quantity,
        public string $unit_price,
        public string $formatted_unit_price,
        public string $price_list_name,
        public string $line_total,
        public string $formatted_line_total,
    ) {}

    /**
     * Expects article.unitOfMeasure and priceList loaded.
     */
    public static function fromModel(WebOrderItem $item): self
    {
        return new self(
            id: $item->id,
            article_id: $item->article_id,
            article_description: $item->article->description,
            article_internal_code: $item->article->internal_code,
            article_image_url: $item->article->image_url,
            unit_of_measure_abbreviation: $item->article->unitOfMeasure->abbreviation,
            quantity: (string) $item->quantity,
            unit_price: $item->unit_price,
            formatted_unit_price: '$ '.number_format((float) $item->unit_price, 2, ',', '.'),
            price_list_name: $item->priceList->name,
            line_total: $item->line_total,
            formatted_line_total: '$ '.number_format((float) $item->line_total, 2, ',', '.'),
        );
    }
}
