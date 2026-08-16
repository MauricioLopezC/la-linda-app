<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Depósito '.ucfirst(fake()->unique()->word()),
            'branch_id' => Branch::factory(),
            'is_online_channel' => false,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the warehouse is assigned to the online channel.
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_online_channel' => true,
        ]);
    }
}
