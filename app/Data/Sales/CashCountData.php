<?php

namespace App\Data\Sales;

use App\Models\Sales\CashCount;
use Spatie\LaravelData\Data;

/**
 * How many bills of one denomination were counted at the opening or closing of a session.
 */
class CashCountData extends Data
{
    public function __construct(
        public int $denomination,
        public string $label,
        public int $quantity,
        public string $subtotal,
    ) {}

    public static function fromModel(CashCount $count): self
    {
        $denomination = $count->denomination();

        return new self(
            denomination: $denomination->value,
            label: $denomination->label(),
            quantity: $count->quantity,
            subtotal: number_format($denomination->value * $count->quantity, 2, '.', ''),
        );
    }
}
