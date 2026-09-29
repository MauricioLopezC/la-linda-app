<?php

namespace App\Data\Sales;

use Spatie\LaravelData\Data;

class SaleVatBreakdownData extends Data
{
    public function __construct(
        public int $vat_rate_id,
        public string $vat_rate,
        public string $vat_rate_description,
        public string $net_amount,
        public string $vat_amount,
        public string $total_amount,
    ) {}
}
