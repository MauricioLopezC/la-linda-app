<?php

namespace App\Data\Sales;

use App\Models\Sales\PointOfSale;
use Spatie\LaravelData\Data;

class PointOfSaleData extends Data
{
    public function __construct(
        public int $id,
        public int $number,
        public int $warehouse_id,
        public string $warehouse_name,
        public string $branch_name,
        public bool $is_active,
    ) {}

    public static function fromModel(PointOfSale $pointOfSale): self
    {
        return new self(
            id: $pointOfSale->id,
            number: $pointOfSale->number,
            warehouse_id: $pointOfSale->warehouse_id,
            warehouse_name: $pointOfSale->warehouse->name,
            branch_name: $pointOfSale->warehouse->branch->name,
            is_active: $pointOfSale->is_active,
        );
    }
}
