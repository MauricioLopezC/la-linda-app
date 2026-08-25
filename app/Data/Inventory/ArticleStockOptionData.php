<?php

namespace App\Data\Inventory;

use Spatie\LaravelData\Data;

class ArticleStockOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $description,
        public string $internal_code,
        public ?string $barcode,
        public string $category_name,
        public ?string $brand_name,
        public string $unit_of_measure_name,
        public string $unit_of_measure_abbreviation,
        public string $current_stock,
    ) {}
}
