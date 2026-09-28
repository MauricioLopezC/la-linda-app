<?php

namespace App\Enums\Sales;

enum CashCountMoment: string
{
    case Opening = 'apertura';
    case Closing = 'cierre';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Apertura',
            self::Closing => 'Cierre',
        };
    }
}
