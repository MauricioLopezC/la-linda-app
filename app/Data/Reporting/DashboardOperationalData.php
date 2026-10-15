<?php

namespace App\Data\Reporting;

use Spatie\LaravelData\Data;

class DashboardOperationalData extends Data
{
    /**
     * @param  array<int, DashboardTopArticleData>  $top_articles
     * @param  array<int, DashboardStockAlertData>  $stock_alerts
     * @param  array<int, DashboardSupplierRankingData>  $supplier_ranking
     */
    public function __construct(
        public array $top_articles,
        public array $stock_alerts,
        public array $supplier_ranking,
        public int $pending_web_orders_count,
    ) {}
}
