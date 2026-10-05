<?php

namespace App\Data\Sales;

use App\Models\Pricing\PriceList;
use Spatie\LaravelData\Data;

class SalePriceListOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $scope,
        public string $scope_label,
        public ?string $channel,
        public ?string $channel_label,
    ) {}

    public static function fromModel(PriceList $priceList): self
    {
        return new self(
            id: $priceList->id,
            name: $priceList->name,
            scope: $priceList->scope->value,
            scope_label: $priceList->scope->label(),
            channel: $priceList->channel?->value,
            channel_label: $priceList->channel?->label(),
        );
    }
}
