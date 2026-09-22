<?php

namespace App\Data\Pricing;

use App\Models\Pricing\PriceList;
use Spatie\LaravelData\Data;

class PriceListData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public string $scope,
        public string $scope_label,
        public ?string $channel,
        public ?string $channel_label,
        public string $valid_from,
        public ?string $valid_to,
        public bool $is_active,
        public string $validity_status,
        public string $validity_status_label,
        public int $articles_with_price_count,
    ) {}

    public static function fromModel(PriceList $priceList): self
    {
        return new self(
            id: $priceList->id,
            name: $priceList->name,
            description: $priceList->description,
            scope: $priceList->scope->value,
            scope_label: $priceList->scope->label(),
            channel: $priceList->channel?->value,
            channel_label: $priceList->channel?->label(),
            valid_from: $priceList->valid_from->toDateString(),
            valid_to: $priceList->valid_to?->toDateString(),
            is_active: $priceList->is_active,
            validity_status: $priceList->validityStatus()->value,
            validity_status_label: $priceList->validityStatus()->label(),
            // Prefer the eager withCount('items') the listing does, and only fall back to a query
            // for the single-list renders that have no aggregate loaded.
            articles_with_price_count: (int) ($priceList->getAttribute('items_count') ?? $priceList->items()->count()),
        );
    }
}
