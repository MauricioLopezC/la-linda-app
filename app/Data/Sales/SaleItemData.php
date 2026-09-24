<?php

namespace App\Data\Sales;

use App\Enums\Pricing\PriceListScope;
use App\Models\Sales\SaleItem;
use Spatie\LaravelData\Data;

class SaleItemData extends Data
{
    public function __construct(
        public int $id,
        public int $article_id,
        public string $article_internal_code,
        public string $article_description,
        public string $unit_of_measure,
        public bool $allows_decimal_quantity,
        public string $quantity,
        public string $unit_price,
        public string $line_total,
        public int $price_list_id,
        public string $price_list_name,
        /** 'canal' | 'particular' */
        public string $price_list_scope,
        /** Label for the price origin badge, e.g. "Mostrador", "General", "Particular: Mayorista". */
        public string $price_origin_label,
    ) {}

    public static function fromModel(SaleItem $item): self
    {
        $priceList = $item->priceList;
        $unitOfMeasure = $item->article->unitOfMeasure;

        $priceOriginLabel = $priceList->scope === PriceListScope::Particular
            ? "Particular: {$priceList->name}"
            : ($priceList->channel?->label() ?? $priceList->name);

        return new self(
            id: $item->id,
            article_id: $item->article_id,
            article_internal_code: $item->article->internal_code,
            article_description: $item->article->description,
            unit_of_measure: $unitOfMeasure->abbreviation,
            allows_decimal_quantity: $unitOfMeasure->allows_decimal_quantity,
            quantity: $item->quantity,
            unit_price: $item->unit_price,
            line_total: $item->line_total,
            price_list_id: $priceList->id,
            price_list_name: $priceList->name,
            price_list_scope: $priceList->scope->value,
            price_origin_label: $priceOriginLabel,
        );
    }
}
