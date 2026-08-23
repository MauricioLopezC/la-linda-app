<?php

namespace App\Data\Inventory;

use Spatie\LaravelData\Data;

class StockBranchTotalData extends Data
{
    /**
     * @param  array<int, StockUnitTotalData>  $quantities_by_unit
     */
    public function __construct(
        public int $branch_id,
        public string $branch_name,
        public int $total_items,
        public int $out_of_stock_count,
        public array $quantities_by_unit,
    ) {}
}
