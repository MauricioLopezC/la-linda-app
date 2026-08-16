<?php

namespace App\Actions\Organization;

use App\Models\Organization\Branch;

class CreateBranch
{
    /**
     * Create a new branch.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Branch
    {
        return Branch::create([
            'name' => (string) $data['name'],
            'address' => isset($data['address']) ? (string) $data['address'] : null,
            'phone' => isset($data['phone']) ? (string) $data['phone'] : null,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
        ]);
    }
}
