<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\StockMovementType;
use Illuminate\Support\Str;

class CreateStockMovementType
{
    /**
     * Create a new custom stock movement type.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): StockMovementType
    {
        $name = (string) $data['name'];
        $code = Str::slug($name, '_');

        // Ensure unique code if slug collides
        $originalCode = $code;
        $counter = 1;
        while (StockMovementType::where('code', $code)->exists()) {
            $code = "{$originalCode}_{$counter}";
            $counter++;
        }

        return StockMovementType::create([
            'name' => $name,
            'code' => $code,
            'sign' => (int) $data['sign'],
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'is_system' => false,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
        ]);
    }
}
