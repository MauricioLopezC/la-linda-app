<?php

namespace App\Enums\Pricing;

enum PriceListValidityStatus: string
{
    case Vigente = 'vigente';
    case Futura = 'futura';
    case Vencida = 'vencida';

    public function label(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::Futura => 'Futura',
            self::Vencida => 'Vencida',
        };
    }
}
