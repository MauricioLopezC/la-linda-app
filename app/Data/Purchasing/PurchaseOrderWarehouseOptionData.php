<?php

namespace App\Data\Purchasing;

use App\Models\Inventory\Warehouse;
use Spatie\LaravelData\Data;

class PurchaseOrderWarehouseOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    public static function fromModel(Warehouse $warehouse): self
    {
        return new self(
            id: $warehouse->id,
            name: $warehouse->name,
        );
    }
}
