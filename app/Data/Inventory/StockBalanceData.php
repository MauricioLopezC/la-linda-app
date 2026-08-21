<?php

namespace App\Data\Inventory;

use App\Models\Inventory\WarehouseStock;
use Spatie\LaravelData\Data;

class StockBalanceData extends Data
{
    public function __construct(
        public int $id,
        public int $article_id,
        public string $article_code,
        public string $article_description,
        public string $category_name,
        public ?string $brand_name,
        public string $unit_of_measure_name,
        public int $warehouse_id,
        public string $warehouse_name,
        public int $branch_id,
        public string $branch_name,
        public float $quantity,
        public float $min_stock,
        public string $status,
        public bool $is_below_min,
        public bool $is_out_of_stock,
    ) {}

    public static function fromModel(WarehouseStock $stock): self
    {
        return new self(
            id: $stock->id,
            article_id: $stock->article_id,
            article_code: $stock->article->internal_code,
            article_description: $stock->article->description,
            category_name: $stock->article->category->name,
            brand_name: $stock->article->brand?->name,
            unit_of_measure_name: $stock->article->unitOfMeasure->name,
            warehouse_id: $stock->warehouse_id,
            warehouse_name: $stock->warehouse->name,
            branch_id: $stock->warehouse->branch_id,
            branch_name: $stock->warehouse->branch->name,
            quantity: $stock->quantity,
            min_stock: $stock->min_stock,
            status: $stock->stockStatus(),
            is_below_min: $stock->isBelowMinimum(),
            is_out_of_stock: $stock->isOutOfStock(),
        );
    }
}
