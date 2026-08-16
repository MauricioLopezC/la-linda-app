<?php

namespace App\Data\Inventory;

use App\Models\Inventory\StockAdjustmentReason;
use Spatie\LaravelData\Data;

class StockAdjustmentReasonData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public bool $is_active,
        public ?string $created_at,
    ) {}

    public static function fromModel(StockAdjustmentReason $model): self
    {
        return new self(
            id: $model->id,
            name: $model->name,
            description: $model->description,
            is_active: $model->is_active,
            created_at: $model->created_at?->format('d/m/Y H:i'),
        );
    }
}
