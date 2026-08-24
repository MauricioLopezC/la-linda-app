<?php

namespace App\Data\Inventory;

use App\Models\Inventory\StockMovement;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class StockMovementDetailData extends Data
{
    /**
     * @param  Collection<int, StockMovementItemDetailData>  $items
     */
    public function __construct(
        public int $id,
        public string $type_name,
        public string $type_code,
        public int $warehouse_id,
        public string $warehouse_name,
        public string $branch_name,
        public ?int $reason_id,
        public ?string $reason_name,
        public ?string $notes,
        public int $user_id,
        public string $user_name,
        public string $created_at,
        public string $created_at_formatted,
        public Collection $items,
    ) {}

    public static function fromModel(StockMovement $movement): self
    {
        return new self(
            id: $movement->id,
            type_name: $movement->type->name,
            type_code: $movement->type->code,
            warehouse_id: $movement->warehouse_id,
            warehouse_name: $movement->warehouse->name,
            branch_name: $movement->warehouse->branch->name,
            reason_id: $movement->stock_adjustment_reason_id,
            reason_name: $movement->reason?->name,
            notes: $movement->notes,
            user_id: $movement->user_id,
            user_name: $movement->user->name,
            created_at: $movement->created_at?->toISOString() ?? now()->toISOString(),
            created_at_formatted: $movement->created_at?->format('d/m/Y H:i:s') ?? now()->format('d/m/Y H:i:s'),
            items: StockMovementItemDetailData::collect($movement->items),
        );
    }
}
