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

        /*
         * The sign is only editable while the type is untouched. System types are protected
         * outright, and once a type has recorded movements flipping its sign would mean "from now
         * on this type means the opposite", which makes the history unreadable. It never changes
         * what already happened -- the signed delta lives in stock_movement_items.quantity -- but
         * it does invalidate the rule the past movements were written under.
         */
        if (! $movementType->is_system && ! $movementType->isInUse() && isset($data['sign'])) {
            $payload['sign'] = (int) $data['sign'];
        }

        $movementType->update($payload);

        return $movementType;
    }
}
