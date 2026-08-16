<?php

namespace App\Actions\Sales;

use App\Models\Sales\PointOfSale;

class TogglePointOfSaleStatus
{
    /**
     * Toggle the active status of a point of sale.
     */
    public function handle(PointOfSale $pointOfSale): PointOfSale
    {
        $pointOfSale->update([
            'is_active' => ! $pointOfSale->is_active,
        ]);

        return $pointOfSale;
    }
}
