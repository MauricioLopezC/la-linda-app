<?php

namespace App\Enums\Ecommerce;

enum OnlineAvailabilityStatus: string
{
    case Available = 'disponible';
    case LastUnits = 'ultimas_unidades';
    case OutOfStock = 'sin_stock';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::LastUnits => 'Últimas unidades',
            self::OutOfStock => 'Sin stock',
        };
    }
}
