<?php

namespace App\Data\Inventory;

use Spatie\LaravelData\Data;

class StockUnitTotalData extends Data
{
    public function __construct(
        public int $unit_id,
        public string $unit_name,
        public string $unit_abbreviation,
        public float $quantity,
    ) {}
}
