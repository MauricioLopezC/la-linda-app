<?php

namespace App\Data\Inventory;

use Spatie\LaravelData\Data;

class StockTotalsData extends Data
{
    /**
     * @param  array<int, StockUnitTotalData>  $quantities_by_unit
     * @param  array<int, StockBranchTotalData>  $branch_totals
     */
    public function __construct(
        public int $grand_total_items,
        public int $total_out_of_stock,
        public array $quantities_by_unit,
        public array $branch_totals,
    ) {}
}
