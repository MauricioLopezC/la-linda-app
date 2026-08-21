<?php

namespace App\Data\Pricing;

use App\Models\Pricing\VatRate;
use Spatie\LaravelData\Data;

class VatRateData extends Data
{
    public function __construct(
        public int $id,
        public string $description,
        public float $percentage,
        public bool $is_active,
    ) {}

    public static function fromModel(VatRate $vatRate): self
    {
        return new self(
            id: $vatRate->id,
            description: $vatRate->description,
            percentage: $vatRate->percentage,
            is_active: $vatRate->is_active,
        );
    }
}
