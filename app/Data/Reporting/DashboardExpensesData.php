<?php

namespace App\Data\Reporting;

use Spatie\LaravelData\Data;

/**
 * Expenses block of the management dashboard (EPIC-19), returned by GetDashboardExpenses.
 *
 * `accrued_total` (supplier invoices + debit notes − credit notes) is what EPIC-20 uses as the
 * expense side of the net result; `disbursed_total` (payment orders + cash expenses) is shown
 * alongside it as the actual cash outflow.
 */
class DashboardExpensesData extends Data
{
    /**
     * @param  array<int, DashboardBreakdownItemData>  $by_payment_method
     */
    public function __construct(
        public string $accrued_total,
        public string $paid_to_suppliers_total,
        public string $cash_expenses_total,
        public string $disbursed_total,
        public array $by_payment_method,
    ) {}
}
