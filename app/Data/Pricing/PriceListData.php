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
        public string $channel,
        public string $channel_label,
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
            channel: $priceList->channel->value,
            channel_label: $priceList->channel->label(),
            valid_from: $priceList->valid_from->toDateString(),
            valid_to: $priceList->valid_to?->toDateString(),
            is_active: $priceList->is_active,
            validity_status: $priceList->validityStatus()->value,
            validity_status_label: $priceList->validityStatus()->label(),
            // Always 0 until HU-012 adds price_list_items and a real items() relation to count.
            articles_with_price_count: 0,
        );
    }
}
