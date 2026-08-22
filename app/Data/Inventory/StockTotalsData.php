<?php

namespace App\Data\Inventory;

use Spatie\LaravelData\Data;

class StockTotalsData extends Data
{
    /**
     * @param  array<int, StockBranchTotalData>  $branch_totals
     */
    public function __construct(
        public float $grand_total_quantity,
        public int $grand_total_items,
        public int $total_low_stock,
        public int $total_out_of_stock,
        public array $branch_totals,
    ) {}
}
