<?php

namespace App\Data\Inventory;

use App\Models\Inventory\StockMovementType;
use Spatie\LaravelData\Data;

class StockMovementTypeData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $code,
        public int $sign,
        public ?string $description,
        public bool $is_system,
        public bool $is_active,
        public ?string $created_at,
    ) {}

    public static function fromModel(StockMovementType $model): self
    {
        return new self(
            id: $model->id,
            name: $model->name,
            code: $model->code,
            sign: $model->sign,
            description: $model->description,
            is_system: $model->is_system,
            is_active: $model->is_active,
            created_at: $model->created_at?->format('d/m/Y H:i'),
        );
    }
}
