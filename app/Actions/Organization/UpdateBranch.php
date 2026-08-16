<?php

namespace App\Actions\Organization;

use App\Models\Organization\Branch;

class UpdateBranch
{
    /**
     * Update an existing branch.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Branch $branch, array $data): Branch
    {
        $branch->update([
            'name' => (string) $data['name'],
            'address' => isset($data['address']) ? (string) $data['address'] : null,
            'phone' => isset($data['phone']) ? (string) $data['phone'] : null,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $branch->is_active,
        ]);

        return $branch;
    }
}
