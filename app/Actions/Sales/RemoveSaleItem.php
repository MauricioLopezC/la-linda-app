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

        if (! $sale->isOpen()) {
            throw ValidationException::withMessages([
                'sale' => 'La venta ya no está abierta y no admite cambios.',
            ]);
        }

        DB::transaction(function () use ($item, $sale): void {
            $item->delete();
            $sale->recalculateTotal();
        });
    }
}
