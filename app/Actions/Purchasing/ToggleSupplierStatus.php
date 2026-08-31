<?php

namespace App\Actions\Purchasing;

use App\Models\Purchasing\Supplier;
use Illuminate\Support\Facades\Log;

class ToggleSupplierStatus
{
    public function handle(Supplier $supplier): Supplier
    {
        $supplier->update([
            'is_active' => ! $supplier->is_active,
        ]);

        Log::info(sprintf(
            'Supplier status toggled [ID: %d, Business Name: %s, is_active: %s] by User ID: %s',
            $supplier->id,
            $supplier->business_name,
            $supplier->is_active ? 'active' : 'inactive',
            auth()->id() ?? 'system'
        ));

        return $supplier;
    }
}
