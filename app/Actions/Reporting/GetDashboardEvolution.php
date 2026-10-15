<?php

namespace App\Actions\Reporting;

use App\Concerns\ConvertsMoneyToCents;
use App\Data\Reporting\DashboardEvolutionData;
use App\Data\Reporting\DashboardEvolutionPointData;
use App\Data\Reporting\DashboardExpensesData;
use App\Data\Reporting\DashboardRevenuesData;
use App\Enums\Sales\SaleChannel;
use Carbon\CarbonImmutable;

/**
 * @phpstan-type DashboardFilters array{date_from: string, date_to: string, branch_id: ?int, channel: ?string}
 */
class GetDashboardEvolution
{
    use ConvertsMoneyToCents;

    /**
     * Ranges up to this many days are charted day by day; longer ones, month by month.
     */
    public const int MAX_DAILY_RANGE_DAYS = 31;

    public function __construct(
        private GetDashboardRevenues $getDashboardRevenues,
        private GetDashboardExpenses $getDashboardExpenses,
    ) {}

    /**
     * Net result of the period and its evolution for the management dashboard (EPIC-20).
     *
     * The net result is the revenues block's `net_revenue` minus the expenses block's
     * `accrued_total`, so it always matches the difference between the EPIC-18 and EPIC-19
     * totals shown on the same page. The series covers every day (or month, for ranges longer
     * than {@see self::MAX_DAILY_RANGE_DAYS} days) of the range, filling gaps with `0.00`.
     *
     * @param  DashboardFilters  $filters
     */
    public function handle(array $filters, DashboardRevenuesData $revenues, DashboardExpensesData $expenses): DashboardEvolutionData
    {
        $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'])->startOfDay();
        $branchId = $filters['branch_id'];
        $channel = $filters['channel'] !== null ? SaleChannel::from($filters['channel']) : null;

        $revenueCents = $this->moneyToCents($revenues->net_revenue);
        $netResultCents = $revenueCents - $this->moneyToCents($expenses->accrued_total);

        $periodDays = (int) $from->diffInDays($to) + 1;
        $previousFrom = $from->subDays($periodDays);
        $previousTo = $from->subDay();
        $previousNetResultCents = array_sum($this->getDashboardRevenues->netRevenueCentsByDay($previousFrom, $previousTo, $branchId, $channel))
            - array_sum($this->getDashboardExpenses->accruedCentsByDay($previousFrom->toDateString(), $previousTo->toDateString()));

        return new DashboardEvolutionData(
            net_result: $this->centsToMoney($netResultCents),
            net_margin_pct: $revenueCents !== 0 ? round($netResultCents / $revenueCents * 100, 1) : null,
            net_result_variation_pct: $this->variationPercentage($netResultCents, $previousNetResultCents),
            series: $this->series($from, $to, $branchId, $channel, $periodDays > self::MAX_DAILY_RANGE_DAYS),
        );
    }

    /**
     * One point per day (`Y-m-d`) or per month (`Y-m`) of the range, with every period present
     * even when it had no movements so the chart never skips one.
     *
     * @return list<DashboardEvolutionPointData>
     */
    private function series(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId, ?SaleChannel $channel, bool $isMonthly): array
    {
        $format = $isMonthly ? 'Y-m' : 'Y-m-d';

        $periods = [];
        $cursor = $isMonthly ? $from->startOfMonth() : $from;

        while ($cursor <= $to) {
            $periods[] = $cursor->format($format);
            $cursor = $isMonthly ? $cursor->addMonth() : $cursor->addDay();
        }

        $revenueByPeriod = $this->sumByPeriod($this->getDashboardRevenues->netRevenueCentsByDay($from, $to, $branchId, $channel), $format);
        $expensesByPeriod = $this->sumByPeriod($this->getDashboardExpenses->accruedCentsByDay($from->toDateString(), $to->toDateString()), $format);

        $series = [];

        foreach ($periods as $period) {
            $revenueCents = $revenueByPeriod[$period] ?? 0;
            $expensesCents = $expensesByPeriod[$period] ?? 0;

            $series[] = new DashboardEvolutionPointData(
                period: $period,
                net_revenue: $this->centsToMoney($revenueCents),
                accrued_expenses: $this->centsToMoney($expensesCents),
                net_result: $this->centsToMoney($revenueCents - $expensesCents),
            );
        }

        return $series;
    }

    /**
     * Regroups amounts keyed by `Y-m-d` day into the periods of the series.
     *
     * @param  array<string, int>  $centsByDay
     * @return array<string, int>
     */
    private function sumByPeriod(array $centsByDay, string $format): array
    {
        $centsByPeriod = [];

        foreach ($centsByDay as $day => $cents) {
            $period = CarbonImmutable::parse($day)->format($format);
            $centsByPeriod[$period] = ($centsByPeriod[$period] ?? 0) + $cents;
        }

        return $centsByPeriod;
    }

    /**
     * Percentage change against the previous period, or null when there is nothing to compare.
     * The base is taken in absolute value so that going from a deficit to a smaller one (or to
     * a surplus) reads as an improvement.
     */
    private function variationPercentage(int $currentCents, int $previousCents): ?float
    {
        if ($previousCents === 0) {
            return null;
        }

        return round(($currentCents - $previousCents) / abs($previousCents) * 100, 1);
    }
}
