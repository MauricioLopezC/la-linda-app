<?php

namespace App\Data\Reporting;

use Spatie\LaravelData\Data;

/**
 * Props of the management dashboard page. Each section block is nullable so it can be wired in
 * by its own story without touching the others; a null block renders as "not available yet".
 */
class DashboardData extends Data
{
    /**
     * @param  array<int, DashboardBranchOptionData>  $branches
     */
    public function __construct(
        public DashboardFiltersData $filters,
        public array $branches,
        public ?DashboardRevenuesData $revenues = null,
        public ?DashboardExpensesData $expenses = null,
    ) {}
}
