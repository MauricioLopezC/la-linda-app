<?php

namespace App\Actions\Reporting;

use App\Concerns\ConvertsMoneyToCents;
use App\Data\Reporting\DashboardOperationalData;
use App\Data\Reporting\DashboardStockAlertData;
use App\Data\Reporting\DashboardSupplierRankingData;
use App\Data\Reporting\DashboardTopArticleData;
use App\Enums\Catalog\ArticleStatus;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Ecommerce\WebOrder;
use App\Models\Inventory\StockBalance;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Sales\SaleItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @phpstan-type DashboardFilters array{date_from: string, date_to: string, branch_id: ?int, channel: ?string}
 */
class GetDashboardOperationalIndicators
{
    use ConvertsMoneyToCents;

    /**
     * Compute operational indicators for the management dashboard (EPIC-21):
     * top sold articles, out of stock/conflict alerts, supplier purchase ranking,
     * and pending online dispatch alerts.
     *
     * @param  DashboardFilters  $filters
     */
    public function handle(array $filters): DashboardOperationalData
    {
        $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();
        $branchId = $filters['branch_id'];
        $channel = $filters['channel'] !== null ? SaleChannel::from($filters['channel']) : null;

        return new DashboardOperationalData(
            top_articles: $this->topArticles($from, $to, $branchId, $channel),
            stock_alerts: $this->stockAlerts($branchId, $channel),
            supplier_ranking: $this->supplierRanking($from, $to, $branchId, $channel),
            pending_web_orders_count: $this->pendingWebOrdersCount($branchId, $channel),
        );
    }

    /**
     * Top sold articles in the period (by sold quantity and invoiced total over confirmed sales).
     *
     * @return array<int, DashboardTopArticleData>
     */
    private function topArticles(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId, ?SaleChannel $channel, int $limit = 10): array
    {
        $rows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('articles', 'articles.id', '=', 'sale_items.article_id')
            ->where('sales.status', SaleStatus::Confirmed)
            ->whereBetween('sales.confirmed_at', [$from, $to])
            ->when($channel !== null, fn (Builder $q) => $q->where('sales.channel', $channel->value))
            ->when($branchId !== null, fn (Builder $q) => $q->whereHas(
                'sale.pointOfSale.warehouse',
                fn (Builder $warehouse) => $warehouse->where('branch_id', $branchId)
            ))
            ->toBase()
            ->selectRaw('articles.id as article_id')
            ->selectRaw('articles.description as description')
            ->selectRaw('SUM(sale_items.quantity) as total_quantity')
            ->selectRaw('SUM(sale_items.line_total) as total_invoiced')
            ->groupBy('articles.id', 'articles.description')
            ->orderByDesc('total_quantity')
            ->orderByDesc('total_invoiced')
            ->limit($limit)
            ->get();

        return $rows->map(fn (object $row): DashboardTopArticleData => new DashboardTopArticleData(
            article_id: (int) $row->article_id,
            description: (string) $row->description,
            quantity: sprintf('%.3f', (float) $row->total_quantity),
            total_invoiced: sprintf('%.2f', (float) $row->total_invoiced),
        ))->all();
    }

    /**
     * Active articles with quantity <= 0 or in stock conflict (negative balance from EPIC-06).
     *
     * @return array<int, DashboardStockAlertData>
     */
    private function stockAlerts(?int $branchId, ?SaleChannel $channel, int $limit = 10): array
    {
        $rows = StockBalance::query()
            ->join('articles', 'articles.id', '=', 'stock_balances.article_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->where('articles.status', ArticleStatus::Active->value)
            ->where('warehouses.is_active', true)
            ->where('stock_balances.quantity', '<=', 0)
            ->when($branchId !== null, fn (Builder $q) => $q->where('warehouses.branch_id', $branchId))
            ->when($channel !== null, function (Builder $q) use ($channel): void {
                if ($channel === SaleChannel::Online) {
                    $q->where('warehouses.is_online_channel', true);
                } else {
                    $q->where('warehouses.is_online_channel', false);
                }
            })
            ->toBase()
            ->select([
                'articles.id as article_id',
                'articles.description as article_description',
                'warehouses.id as warehouse_id',
                'warehouses.name as warehouse_name',
                'stock_balances.quantity as quantity',
            ])
            ->orderBy('stock_balances.quantity', 'asc')
            ->orderBy('articles.description', 'asc')
            ->limit($limit)
            ->get();

        return $rows->map(fn (object $row): DashboardStockAlertData => new DashboardStockAlertData(
            article_id: (int) $row->article_id,
            article_description: (string) $row->article_description,
            warehouse_id: (int) $row->warehouse_id,
            warehouse_name: (string) $row->warehouse_name,
            quantity: sprintf('%.3f', (float) $row->quantity),
            has_stock_conflict: (float) $row->quantity < 0,
        ))->all();
    }

    /**
     * Ranking of supplier purchases in the period and current outstanding account balance.
     *
     * @return array<int, DashboardSupplierRankingData>
     */
    private function supplierRanking(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId, ?SaleChannel $channel, int $limit = 10): array
    {
        $vouchers = SupplierVoucher::query()
            ->select(['id', 'supplier_id', 'type', 'total_amount'])
            ->whereIn('type', [
                SupplierVoucherType::Invoice,
                SupplierVoucherType::DebitNote,
                SupplierVoucherType::CreditNote,
            ])
            ->where('status', '!=', SupplierVoucherStatus::Cancelled)
            ->whereDate('issue_date', '>=', $from->toDateString())
            ->whereDate('issue_date', '<=', $to->toDateString())
            ->when($branchId !== null, fn (Builder $q) => $q->where(function (Builder $sq) use ($branchId): void {
                $sq->whereNull('warehouse_id')
                    ->orWhereHas('warehouse', fn (Builder $w) => $w->where('branch_id', $branchId));
            }))
            ->get();

        /** @var array<int, int> $supplierPurchasesCents */
        $supplierPurchasesCents = [];

        foreach ($vouchers as $voucher) {
            $amountCents = $this->moneyToCents($voucher->total_amount);
            $supplierPurchasesCents[$voucher->supplier_id] = ($supplierPurchasesCents[$voucher->supplier_id] ?? 0)
                + ($voucher->type === SupplierVoucherType::CreditNote ? -$amountCents : $amountCents);
        }

        if (empty($supplierPurchasesCents)) {
            return [];
        }

        arsort($supplierPurchasesCents);
        $topSupplierIds = array_slice(array_keys($supplierPurchasesCents), 0, $limit, true);

        $suppliers = Supplier::query()
            ->whereIn('id', $topSupplierIds)
            ->get()
            ->keyBy('id');

        $payableTypes = array_map(
            fn (SupplierVoucherType $type): string => $type->value,
            array_filter(
                SupplierVoucherType::cases(),
                fn (SupplierVoucherType $type): bool => $type->createsPayableBalance(),
            ),
        );

        $pendingVouchers = SupplierVoucher::query()
            ->withBalanceAggregates()
            ->whereIn('supplier_id', $topSupplierIds)
            ->whereIn('type', $payableTypes)
            ->where('status', '!=', SupplierVoucherStatus::Cancelled->value)
            ->get();

        /** @var array<int, int> $supplierOutstandingCents */
        $supplierOutstandingCents = [];
        foreach ($pendingVouchers as $voucher) {
            $supplierOutstandingCents[$voucher->supplier_id] = ($supplierOutstandingCents[$voucher->supplier_id] ?? 0)
                + $this->moneyToCents($voucher->outstandingAmount());
        }

        $ranking = [];
        foreach ($topSupplierIds as $supplierId) {
            $supplier = $suppliers->get($supplierId);
            if ($supplier === null) {
                continue;
            }

            $purchasesCents = $supplierPurchasesCents[$supplierId];
            $outstandingCents = $supplierOutstandingCents[$supplierId] ?? 0;

            $ranking[] = new DashboardSupplierRankingData(
                supplier_id: $supplier->id,
                name: $supplier->business_name,
                purchases_total: $this->centsToMoney(max(0, $purchasesCents)),
                outstanding_balance: $this->centsToMoney($outstandingCents),
            );
        }

        return $ranking;
    }

    /**
     * Quantity of online web orders in paid or in_preparation status pending delivery in warehouse.
     */
    private function pendingWebOrdersCount(?int $branchId, ?SaleChannel $channel): int
    {
        if ($channel === SaleChannel::Mostrador) {
            return 0;
        }

        return WebOrder::query()
            ->whereIn('status', [WebOrderStatus::Paid, WebOrderStatus::InPreparation])
            ->when($branchId !== null, function (Builder $query) use ($branchId): void {
                $query->where(function (Builder $subQuery) use ($branchId): void {
                    $subQuery->where('pickup_branch_id', $branchId)
                        ->orWhere(function (Builder $nested) use ($branchId): void {
                            $nested->whereNull('pickup_branch_id')
                                ->whereHas('sale.pointOfSale.warehouse', fn (Builder $w) => $w->where('branch_id', $branchId));
                        });
                });
            })
            ->count();
    }
}
