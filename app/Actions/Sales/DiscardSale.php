<?php

namespace App\Actions\Sales;

use App\Enums\Sales\SaleStatus;
use App\Models\Sales\Sale;
use Illuminate\Validation\ValidationException;

class DiscardSale
{
    /**
     * Discard an open sale. Its lines are kept for reference; the sale becomes read-only.
     */
    public function handle(Sale $sale): Sale
    {
        if (! $sale->isOpen()) {
            throw ValidationException::withMessages([
                'sale' => 'Solo se puede descartar una venta abierta.',
            ]);
        }

        $sale->update(['status' => SaleStatus::Discarded]);

        return $sale;
    }
}
