<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\StockAdjustmentReason;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementType;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_movement_type_id' => StockMovementType::factory(),
            'warehouse_id' => Warehouse::factory(),
            'stock_adjustment_reason_id' => null,
            'notes' => null,
            'user_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the movement is an inventory adjustment, which requires a reason.
     *
     * The type is resolved with firstOrCreate so the state works whether or not the test already
     * seeded the system movement types: stock_movement_types.code is unique.
     */
    public function adjustment(): static
    {
        return $this->state(fn (array $attributes): array => [
            'stock_movement_type_id' => StockMovementType::firstOrCreate(
                ['code' => StockMovementType::CODE_INVENTORY_ADJUSTMENT],
                [
                    'name' => 'Ajuste de inventario',
                    'sign' => 1,
                    'description' => 'Ajuste de existencias por diferencia física documentada',
                    'is_system' => true,
                    'is_active' => true,
                ]
            )->id,
            'stock_adjustment_reason_id' => StockAdjustmentReason::factory(),
        ]);
    }
}
