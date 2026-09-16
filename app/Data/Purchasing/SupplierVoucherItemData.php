<?php

namespace App\Data\Purchasing;

use App\Models\Purchasing\SupplierVoucherItem;
use Spatie\LaravelData\Data;

class SupplierVoucherItemData extends Data
{
    public function __construct(
        public int $id,
        public int $position,
        public ?int $article_id,
        public ?string $article_internal_code,
        public string $description,
        public string $quantity,
        public string $unit_of_measure,
        public string $unit_price,
        public string $line_total,
    ) {}

    public static function fromModel(SupplierVoucherItem $item): self
    {
        return new self(
            id: $item->id,
            position: $item->position,
            article_id: $item->article_id,
            article_internal_code: $item->article?->internal_code,
            description: $item->description,
            quantity: (string) $item->quantity,
            unit_of_measure: $item->unit_of_measure,
            unit_price: (string) $item->unit_price,
            line_total: (string) $item->line_total,
        );
    }
}
