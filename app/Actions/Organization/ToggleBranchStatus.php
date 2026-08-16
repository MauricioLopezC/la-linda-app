<?php

namespace App\Actions\Organization;

use App\Models\Organization\Branch;
use Illuminate\Validation\ValidationException;

class ToggleBranchStatus
{
    /**
     * Toggle the active status of a branch, blocking deactivation when it has
     * registered stock or movements.
     *
     * @throws ValidationException
     */
    public function handle(Branch $branch): Branch
    {
        if ($branch->is_active && $branch->hasRegisteredStock()) {
            throw ValidationException::withMessages([
                'branch' => 'No se puede dar de baja una sucursal con existencias o movimientos registrados.',
            ]);
        }

        $branch->update([
            'is_active' => ! $branch->is_active,
        ]);

        return $branch;
    }
}
