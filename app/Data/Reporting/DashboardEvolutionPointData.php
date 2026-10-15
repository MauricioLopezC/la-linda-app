<?php

namespace App\Data\Reporting;

use Spatie\LaravelData\Data;

class DashboardEvolutionPointData extends Data
{
    public function __construct(
        public string $period,
        public string $net_revenue,
        public string $accrued_expenses,
        public string $net_result,
    ) {}
}
