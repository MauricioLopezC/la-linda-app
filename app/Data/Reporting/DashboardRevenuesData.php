<?php

namespace App\Data\Reporting;

use Spatie\LaravelData\Data;

/**
 * Revenues block of the management dashboard (EPIC-18), returned by GetDashboardRevenues.
 *
 * Only confirmed sales of the period take part: open or discarded sales and pending web orders
 * are excluded. `net_revenue` is what EPIC-20 uses as the income side of the net result.
 */
class DashboardRevenuesData extends Data
{
    /**
     * @param  array<int, DashboardBreakdownItemData>  $by_channel
     * @param  array<int, DashboardBreakdownItemData>  $by_branch
     * @param  array<int, DashboardBreakdownItemData>  $by_payment_method
     */
    public function __construct(
        public string $total_invoiced,
        public string $net_revenue,
        public string $vat_total,
        public int $confirmed_sales_count,
        public string $average_ticket,
        public ?float $total_invoiced_variation_pct,
        public array $by_channel,
        public array $by_branch,
        public array $by_payment_method,
    ) {}
}
