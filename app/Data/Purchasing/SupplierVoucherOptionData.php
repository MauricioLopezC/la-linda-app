<?php

namespace App\Data\Purchasing;

use Spatie\LaravelData\Data;

class SupplierVoucherOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}
}
