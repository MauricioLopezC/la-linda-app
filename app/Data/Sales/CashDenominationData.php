<?php

namespace App\Data\Sales;

use App\Enums\Sales\CashDenomination;
use Spatie\LaravelData\Data;

class CashDenominationData extends Data
{
    public function __construct(
        public int $value,
        public string $label,
    ) {}

    public static function fromEnum(CashDenomination $denomination): self
    {
        return new self(
            value: $denomination->value,
            label: $denomination->label(),
        );
    }
}
