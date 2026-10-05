<?php

namespace App\Data\Inventory;

use App\Models\Inventory\StockMovementItem;
use Spatie\LaravelData\Data;

class StockMovementItemDetailData extends Data
{
    public function __construct(
        public int $id,
        public int $article_id,
        public string $article_description,
        public string $article_internal_code,
        public ?string $article_barcode,
        public string $category_name,
        public ?string $brand_name,
        public string $unit_of_measure_name,
        public string $unit_of_measure_abbreviation,
        public string $quantity,
        public ?string $system_quantity,
        public bool $is_conflict,
    ) {}

    public static function fromModel(StockMovementItem $item): self
    {
        return new self(
            id: $item->id,
            article_id: $item->article_id,
            article_description: $item->article->description,
            article_internal_code: $item->article->internal_code,
            article_barcode: $item->article->barcode,
            category_name: $item->article->category->name,
            brand_name: $item->article->brand?->name,
            unit_of_measure_name: $item->article->unitOfMeasure->name,
            unit_of_measure_abbreviation: $item->article->unitOfMeasure->abbreviation,
            quantity: sprintf('%.3f', (float) $item->quantity),
            system_quantity: $item->system_quantity !== null ? sprintf('%.3f', (float) $item->system_quantity) : null,
            is_conflict: $item->hasStockConflict(),
        );
    }
}
