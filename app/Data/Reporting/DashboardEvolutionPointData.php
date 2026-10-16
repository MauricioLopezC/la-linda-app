<?php

namespace App\Data\Reporting;

use Spatie\LaravelData\Data;

/**
 * One period of the EPIC-20 series. `accrued_expenses` is `accrued_charges` (invoices + debit
 * notes) minus `credit_notes`; `cumulative_net_result` adds up `net_result` from the start of the
 * range, so the last point equals the period's net result.
 */
class DashboardEvolutionPointData extends Data
{
    public function __construct(
        public string $period,
        public string $net_revenue,
        public string $accrued_expenses,
        public string $net_result,
        public string $accrued_charges,
        public string $credit_notes,
        public string $cumulative_net_result,
    ) {}
}
