<?php

namespace App\Data\Ecommerce;

use App\Models\Inventory\StockMovement;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

class WarehouseWebOrderMovementData extends Data
{
    public function __construct(
        public int $id,
        public string $type_code,
        public string $type_name,
        public int $sale_id,
        public int $warehouse_id,
        public string $warehouse_name,
    ) {}

    public static function fromModel(StockMovement $movement): self
    {
        return new self(
            id: $movement->id,
            type_code: $movement->type->code,
            type_name: $movement->type->name,
            sale_id: $movement->sale_id ?? throw new InvalidArgumentException('El movimiento de entrega debe tener una venta asociada.'),
            warehouse_id: $movement->warehouse_id,
            warehouse_name: $movement->warehouse->name,
        );
    }
}
