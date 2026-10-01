<?php

namespace App\Actions\Sales;

use App\Models\Sales\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveSaleItem
{
    /**
     * Remove a line from an open sale and update its total.
     */
    public function handle(SaleItem $item): void
    {
        $sale = $item->sale;

        if (! $sale->acceptsChanges()) {
            throw ValidationException::withMessages([
                'sale' => 'La venta no admite cambios porque está cerrada o su turno de caja no está abierto.',
            ]);
        }

        DB::transaction(function () use ($item, $sale): void {
            $item->delete();
            $sale->recalculateTotal();
        });
    }
}
