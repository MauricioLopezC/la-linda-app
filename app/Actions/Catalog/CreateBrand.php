<?php

namespace App\Actions\Catalog;

use App\Models\Catalog\Brand;

class CreateBrand
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Brand
    {
        return Brand::create([
            'name' => (string) $data['name'],
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
        ]);
    }
}
