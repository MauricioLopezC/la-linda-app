<?php

namespace App\Data\Inventory;

use Spatie\LaravelData\Data;

class StockBranchTotalData extends Data
{
    public function __construct(
        public int $branch_id,
        public string $branch_name,
        public int $total_items,
        public int $in_stock_count,
        public int $out_of_stock_count,
    ) {}
}
