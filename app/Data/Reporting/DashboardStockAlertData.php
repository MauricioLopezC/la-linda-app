<?php

namespace App\Data\Reporting;

use Spatie\LaravelData\Data;

class DashboardStockAlertData extends Data
{
    public function __construct(
        public int $article_id,
        public string $article_description,
        public int $warehouse_id,
        public string $warehouse_name,
        public string $quantity,
        public bool $has_stock_conflict,
    ) {}
}
