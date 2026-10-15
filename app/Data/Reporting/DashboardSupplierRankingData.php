<?php

namespace App\Data\Reporting;

use Spatie\LaravelData\Data;

class DashboardSupplierRankingData extends Data
{
    public function __construct(
        public int $supplier_id,
        public string $name,
        public string $purchases_total,
        public string $outstanding_balance,
    ) {}
}
