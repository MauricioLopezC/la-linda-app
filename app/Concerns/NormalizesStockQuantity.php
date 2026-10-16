<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Validator;

trait NormalizesStockQuantity
{
    /** @return numeric-string */
    protected function normalizeStockQuantity(float|int|string $quantity): string
    {
        Validator::make(['quantity' => $quantity], [
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3', 'max:999999999.999'],
        ], [
            'quantity.gt' => 'La cantidad debe ser mayor a cero.',
            'quantity.decimal' => 'La cantidad admite hasta tres decimales.',
        ])->validate();

        $value = (string) $quantity;
        assert(is_numeric($value));

        return bcadd($value, '0', 3);
    }
}
