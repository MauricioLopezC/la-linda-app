<?php

namespace App\Data\Ecommerce;

use Spatie\LaravelData\Data;

class CartData extends Data
{
    /**
     * @param  array<int, CartItemData>  $items
     */
    public function __construct(
        public array $items,
        public string $total,
        public string $formatted_total,
        public int $lines_count,
        public string $total_quantity,
        public bool $has_unavailable_items,
    ) {}
}
