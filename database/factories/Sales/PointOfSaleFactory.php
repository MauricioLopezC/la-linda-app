<?php

namespace Database\Factories\Sales;

use App\Models\Inventory\Warehouse;
use App\Models\Sales\PointOfSale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointOfSale>
 */
class PointOfSaleFactory extends Factory
{
    protected $model = PointOfSale::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(1, 999),
            'warehouse_id' => Warehouse::factory(),
            'is_active' => true,
        ];
    }
}
