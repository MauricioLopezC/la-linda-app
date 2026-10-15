<?php

namespace App\Actions\Reporting;

use App\Concerns\ConvertsMoneyToCents;
use App\Data\Reporting\DashboardBreakdownItemData;
use App\Data\Reporting\DashboardRevenuesData;
use App\Enums\Sales\CashMovementType;
use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Sales\CashMovement;
use App\Models\Sales\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @phpstan-type DashboardFilters array{date_from: string, date_to: string, branch_id: ?int, channel: ?string}
 * @phpstan-type BreakdownRow array{key: string, label: string, cents: int, count: int}
 */
class GetDashboardRevenues
{
    use ConvertsMoneyToCents;

    /**
     * Revenues of the period for the management dashboard (EPIC-18).
     *
     * Only confirmed sales take part, by their `confirmed_at`: open and discarded sales are left
     * out, and a pending web order has no sale yet. What was invoiced comes from the invoices of
     * those sales (so shipping and VAT match the voucher), and the breakdown by payment method is
     * what was collected: the `venta` cash movements of counter sales plus the online sales,
     * which are always collected through Mercado Pago and have no cash movement.
     *
     * @param  DashboardFilters  $filters
     */
    public function handle(array $filters): DashboardRevenuesData
    {
        $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();
        $branchId = $filters['branch_id'];
        $channel = $filters['channel'] !== null ? SaleChannel::from($filters['channel']) : null;

        $totals = $this->invoicedTotals($this->confirmedSales($from, $to, $branchId, $channel));

        $periodDays = (int) $from->diffInDays($to->startOfDay()) + 1;
        $previousTotals = $this->invoicedTotals($this->confirmedSales(
            $from->subDays($periodDays),
            $from->subDay()->endOfDay(),
            $branchId,
            $channel,
        ));

        return new DashboardRevenuesData(
            total_invoiced: $this->centsToMoney($totals['total_cents']),
            net_revenue: $this->centsToMoney($totals['net_cents']),
            vat_total: $this->centsToMoney($totals['vat_cents']),
            confirmed_sales_count: $totals['count'],
            average_ticket: $this->centsToMoney($totals['count'] > 0 ? (int) round($totals['total_cents'] / $totals['count']) : 0),
            total_invoiced_variation_pct: $this->variationPercentage($totals['total_cents'], $previousTotals['total_cents']),
            by_channel: $this->toBreakdown($this->byChannel($from, $to, $branchId, $channel)),
            by_branch: $this->toBreakdown($this->byBranch($from, $to, $branchId, $channel)),
            by_payment_method: $this->toBreakdown($this->byPaymentMethod($from, $to, $branchId, $channel)),
        );
    }

    /**
     * Net invoiced revenue (without VAT) of the confirmed sales between two dates, in cents and
     * keyed by `Y-m-d` day; days without sales are left out. EPIC-20 builds its evolution series
     * and the previous-period net result from it, so it follows the same rules as `net_revenue`.
     *
     * @return array<string, int>
     */
    public function netRevenueCentsByDay(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId, ?SaleChannel $channel): array
    {
        return $this->confirmedSales($from->startOfDay(), $to->endOfDay(), $branchId, $channel)
            ->join('invoices', 'invoices.sale_id', '=', 'sales.id')
            ->toBase()
            ->selectRaw('DATE(sales.confirmed_at) as day')
            ->selectRaw($this->sumCents('invoices.net_amount').' as net_cents')
            ->groupByRaw('DATE(sales.confirmed_at)')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->day => (int) $row->net_cents])
            ->all();
    }

    /**
     * Confirmed sales of the period, narrowed to a branch (through point of sale → warehouse)
     * and a channel when they are given.
     *
     * @return Builder<Sale>
     */
    private function confirmedSales(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId, ?SaleChannel $channel): Builder
    {
        return Sale::query()
            ->where('sales.status', SaleStatus::Confirmed)
            ->whereBetween('sales.confirmed_at', [$from, $to])
            ->when($channel !== null, fn (Builder $query) => $query->where('sales.channel', $channel))
            ->when($branchId !== null, fn (Builder $query) => $query->whereHas(
                'pointOfSale.warehouse',
                fn (Builder $warehouse) => $warehouse->where('branch_id', $branchId),
            ));
    }

    /**
     * @param  Builder<Sale>  $sales
     * @return array{total_cents: int, net_cents: int, vat_cents: int, count: int}
     */
    private function invoicedTotals(Builder $sales): array
    {
        $row = $sales
            ->join('invoices', 'invoices.sale_id', '=', 'sales.id')
            ->toBase()
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw($this->sumCents('invoices.total_amount').' as total_cents')
            ->selectRaw($this->sumCents('invoices.net_amount').' as net_cents')
            ->selectRaw($this->sumCents('invoices.vat_amount').' as vat_cents')
            ->first();

        return [
            'total_cents' => (int) ($row->total_cents ?? 0),
            'net_cents' => (int) ($row->net_cents ?? 0),
            'vat_cents' => (int) ($row->vat_cents ?? 0),
            'count' => (int) ($row->sales_count ?? 0),
        ];
    }

    /**
     * Every channel in scope, even at $0, so the comparison always shows both sides.
     *
     * @return list<BreakdownRow>
     */
    private function byChannel(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId, ?SaleChannel $channel): array
    {
        $rows = $this->confirmedSales($from, $to, $branchId, $channel)
            ->join('invoices', 'invoices.sale_id', '=', 'sales.id')
            ->toBase()
            ->select('sales.channel')
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw($this->sumCents('invoices.total_amount').' as total_cents')
            ->groupBy('sales.channel')
            ->get()
            ->keyBy('channel');

        $channels = $channel !== null ? [$channel] : SaleChannel::cases();

        return array_map(fn (SaleChannel $case): array => [
            'key' => $case->value,
            'label' => $case->label(),
            'cents' => (int) ($rows->get($case->value)->total_cents ?? 0),
            'count' => (int) ($rows->get($case->value)->sales_count ?? 0),
        ], $channels);
    }

    /**
     * @return list<BreakdownRow>
     */
    private function byBranch(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId, ?SaleChannel $channel): array
    {
        $rows = $this->confirmedSales($from, $to, $branchId, $channel)
            ->join('invoices', 'invoices.sale_id', '=', 'sales.id')
            ->join('points_of_sale', 'points_of_sale.id', '=', 'sales.point_of_sale_id')
            ->join('warehouses', 'warehouses.id', '=', 'points_of_sale.warehouse_id')
            ->join('branches', 'branches.id', '=', 'warehouses.branch_id')
            ->toBase()
            ->select('branches.id', 'branches.name')
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw($this->sumCents('invoices.total_amount').' as total_cents')
            ->groupBy('branches.id', 'branches.name')
            ->get();

        return $this->sortByAmount($rows->map(fn (object $row): array => [
            'key' => 'branch:'.$row->id,
            'label' => (string) $row->name,
            'cents' => (int) $row->total_cents,
            'count' => (int) $row->sales_count,
        ])->all());
    }

    /**
     * Collected money by payment method: counter payments by the method of each `venta` cash
     * movement (a sale paid with two methods counts in both), plus online sales as Mercado Pago.
     *
     * @return list<BreakdownRow>
     */
    private function byPaymentMethod(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId, ?SaleChannel $channel): array
    {
        $rows = [];

        if ($channel !== SaleChannel::Online) {
            $counterSales = $this->confirmedSales($from, $to, $branchId, SaleChannel::Mostrador)->select('sales.id');

            $rows = CashMovement::query()
                ->where('cash_movements.type', CashMovementType::Sale)
                ->whereIn('cash_movements.sale_id', $counterSales)
                ->join('payment_methods', 'payment_methods.id', '=', 'cash_movements.payment_method_id')
                ->toBase()
                ->select('payment_methods.id', 'payment_methods.name')
                ->selectRaw('COUNT(DISTINCT cash_movements.sale_id) as sales_count')
                ->selectRaw($this->sumCents('cash_movements.amount').' as total_cents')
                ->groupBy('payment_methods.id', 'payment_methods.name')
                ->get()
                ->map(fn (object $row): array => [
                    'key' => 'payment_method:'.$row->id,
                    'label' => (string) $row->name,
                    'cents' => (int) $row->total_cents,
                    'count' => (int) $row->sales_count,
                ])
                ->all();
        }

        if ($channel !== SaleChannel::Mostrador) {
            $online = $this->confirmedSales($from, $to, $branchId, SaleChannel::Online)
                ->toBase()
                ->selectRaw('COUNT(*) as sales_count')
                ->selectRaw($this->sumCents('sales.total_amount').' as total_cents')
                ->first();

            if ((int) ($online->sales_count ?? 0) > 0) {
                $rows[] = [
                    'key' => 'mercado_pago_online',
                    'label' => 'Mercado Pago (online)',
                    'cents' => (int) $online->total_cents,
                    'count' => (int) $online->sales_count,
                ];
            }
        }

        return $this->sortByAmount($rows);
    }

    /**
     * Sum of a money column in integer cents, rounding each row so float storage (SQLite)
     * never drifts and Postgres numeric stays exact.
     *
     * @param  literal-string  $column
     * @return literal-string
     */
    private function sumCents(string $column): string
    {
        return "COALESCE(SUM(CAST(ROUND({$column} * 100) AS BIGINT)), 0)";
    }

    /**
     * Percentage change against the previous period, or null when there is nothing to compare.
     */
    private function variationPercentage(int $currentCents, int $previousCents): ?float
    {
        if ($previousCents === 0) {
            return null;
        }

        return round(($currentCents - $previousCents) / $previousCents * 100, 1);
    }

    /**
     * @param  array<int, BreakdownRow>  $rows
     * @return list<BreakdownRow>
     */
    private function sortByAmount(array $rows): array
    {
        usort($rows, fn (array $a, array $b): int => [$b['cents'], $a['label']] <=> [$a['cents'], $b['label']]);

        return $rows;
    }

    /**
     * @param  list<BreakdownRow>  $rows
     * @return list<DashboardBreakdownItemData>
     */
    private function toBreakdown(array $rows): array
    {
        return array_map(fn (array $row): DashboardBreakdownItemData => new DashboardBreakdownItemData(
            key: $row['key'],
            label: $row['label'],
            amount: $this->centsToMoney($row['cents']),
            count: $row['count'],
        ), $rows);
    }
}
