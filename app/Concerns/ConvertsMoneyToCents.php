<?php

namespace App\Concerns;

use Illuminate\Support\Str;
use InvalidArgumentException;

trait ConvertsMoneyToCents
{
    /**
     * Parse a decimal money string into an integer amount of cents.
     *
     * Accepts an optional leading minus sign, a comma or dot as the decimal
     * separator and up to two decimal places. Throws when the string is not a
     * well-formed money amount so callers never operate on silent zeros.
     */
    protected function moneyToCents(string $amount): int
    {
        $normalized = Str::of($amount)->trim()->replace(',', '.')->toString();

        if (preg_match('/^-?\d+(?:\.\d{1,2})?$/', $normalized) !== 1) {
            throw new InvalidArgumentException("El importe '{$amount}' no tiene un formato monetario válido.");
        }

        $isNegative = str_starts_with($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($normalized, '-'), 2), 2, '');
        $cents = ((int) $whole * 100) + (int) Str::padRight($fraction, 2, '0');

        return $isNegative ? -$cents : $cents;
    }

    /**
     * Format an integer amount of cents back into a decimal money string with a
     * dot separator and exactly two decimal places.
     */
    protected function centsToMoney(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
