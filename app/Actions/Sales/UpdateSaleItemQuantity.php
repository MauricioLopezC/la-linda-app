<?php

namespace App\Actions\Sales;

use App\Models\Sales\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateSaleItemQuantity
{
    /**
     * Change the quantity of a sale line, keeping its snapshot price.
     */
    public function handle(SaleItem $item, float $quantity): SaleItem
    {
        if (! $item->sale->isOpen()) {
            throw ValidationException::withMessages([
                'quantity' => 'La venta ya no está abierta y no admite cambios.',
            ]);
        }

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad debe ser mayor a cero.',
            ]);
        }

        if (! $item->article->unitOfMeasure->allows_decimal_quantity && floor($quantity) !== $quantity) {
            throw ValidationException::withMessages([
                'quantity' => "El artículo \"{$item->article->description}\" solo admite cantidades enteras.",
            ]);
        }

        return DB::transaction(function () use ($item, $quantity): SaleItem {
            $quantityString = number_format($quantity, 3, '.', '');

            $item->update([
                'quantity' => $quantityString,
                'line_total' => SaleItem::calculateLineTotal($quantityString, $item->unit_price),
            ]);

            $item->sale->recalculateTotal();

            return $item;
        });
    }
}
