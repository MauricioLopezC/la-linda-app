<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\StockMovementType;

class UpdateStockMovementType
{
    /**
     * Update an existing stock movement type.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(StockMovementType $movementType, array $data): StockMovementType
    {
        $payload = [
            'name' => (string) $data['name'],
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $movementType->is_active,
        ];

        // For system types, do not allow changing the sign as it would corrupt core business operations
        if (! $movementType->is_system && isset($data['sign'])) {
            $payload['sign'] = (int) $data['sign'];
        }

        $movementType->update($payload);

        return $movementType;
    }
}
