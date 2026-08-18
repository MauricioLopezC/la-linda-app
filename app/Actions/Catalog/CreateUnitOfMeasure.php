<?php

namespace App\Actions\Catalog;

use App\Models\Catalog\UnitOfMeasure;

class CreateUnitOfMeasure
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): UnitOfMeasure
    {
        return UnitOfMeasure::create([
            'name' => (string) $data['name'],
            'abbreviation' => (string) $data['abbreviation'],
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
        ]);
    }
}
