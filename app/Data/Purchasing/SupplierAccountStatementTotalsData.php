<?php

namespace App\Data\Purchasing;

use Spatie\LaravelData\Data;

class SupplierAccountStatementTotalsData extends Data
{
    public function __construct(
        public int $supplier_id,
        public string $supplier_business_name,
        public string $total_received,
        public string $total_paid,
        public string $balance,
    ) {}
}
