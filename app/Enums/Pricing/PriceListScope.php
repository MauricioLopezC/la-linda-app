<?php

namespace App\Enums\Pricing;

enum PriceListScope: string
{
    /**
     * Base list of a sales channel: it answers "¿por dónde se vende?".
     * Exactly one can be active and in effect per channel at any given date.
     */
    case Canal = 'canal';

    /**
     * Preferential list assigned to specific customers: it answers "¿a quién se le vende?".
     * It is never resolved by channel, so several may coexist over the same period.
     */
    case Particular = 'particular';

    public function label(): string
    {
        return match ($this) {
            self::Canal => 'De canal',
            self::Particular => 'Particular',
        };
    }
}
