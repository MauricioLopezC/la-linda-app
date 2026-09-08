<?php

namespace Database\Factories\Purchasing;

use App\Models\Catalog\Article;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\SupplierVoucherItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupplierVoucherItem> */
class SupplierVoucherItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 50);
        $unitPrice = fake()->randomFloat(2, 100, 10_000);

        return [
            'supplier_voucher_id' => SupplierVoucher::factory(),
            'position' => 1,
            'article_id' => Article::factory(),
            'description' => fake()->sentence(4),
            'quantity' => number_format($quantity, 3, '.', ''),
            'unit_of_measure' => 'u',
            'unit_price' => number_format($unitPrice, 2, '.', ''),
            'line_total' => number_format($quantity * $unitPrice, 2, '.', ''),
        ];
    }

    public function concept(): static
    {
        return $this->state(fn (): array => [
            'article_id' => null,
            'description' => 'Cargo administrativo',
            'quantity' => '1.000',
            'unit_of_measure' => 'servicio',
        ]);
    }
}
