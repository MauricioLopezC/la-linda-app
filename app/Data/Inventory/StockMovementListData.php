<?php

namespace App\Data\Inventory;

use App\Models\Inventory\StockMovement;
use Spatie\LaravelData\Data;

class StockMovementListData extends Data
{
    public function __construct(
        public int $id,
        public string $type_name,
        public string $type_code,
        public string $warehouse_name,
        public string $branch_name,
        public ?string $reason_name,
        public ?string $notes,
        public string $user_name,
        public string $created_at,
        public string $created_at_formatted,
        public int $items_count,
        public string $total_quantity,
    ) {}

    public static function fromModel(StockMovement $movement): self
    {
        $totalQty = $movement->items->sum(fn ($item): float => abs((float) $item->quantity));

        return new self(
            id: $movement->id,
            type_name: $movement->type->name,
            type_code: $movement->type->code,
            warehouse_name: $movement->warehouse->name,
            branch_name: $movement->warehouse->branch->name,
            reason_name: $movement->reason?->name,
            notes: $movement->notes,
            user_name: $movement->user->name,
            created_at: $movement->created_at?->toISOString() ?? now()->toISOString(),
            created_at_formatted: $movement->created_at?->format('d/m/Y H:i:s') ?? now()->format('d/m/Y H:i:s'),
            items_count: $movement->items_count ?? $movement->items->count(),
            total_quantity: sprintf('%.3f', $totalQty),
        );
    }
}
