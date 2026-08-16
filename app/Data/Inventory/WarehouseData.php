<?php

namespace App\Data\Inventory;

use App\Models\Inventory\Warehouse;
use Spatie\LaravelData\Data;

class WarehouseData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public int $branch_id,
        public string $branch_name,
        public bool $is_online_channel,
        public bool $is_active,
    ) {}

    public static function fromModel(Warehouse $warehouse): self
    {
        return new self(
            id: $warehouse->id,
            name: $warehouse->name,
            branch_id: $warehouse->branch_id,
            branch_name: $warehouse->branch->name,
            is_online_channel: $warehouse->is_online_channel,
            is_active: $warehouse->is_active,
        );
    }
}
