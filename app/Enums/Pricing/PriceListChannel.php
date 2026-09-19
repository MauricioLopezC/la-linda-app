<?php

namespace App\Enums\Pricing;

enum PriceListChannel: string
{
    case General = 'general';
    case Mostrador = 'mostrador';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Mostrador => 'Mostrador',
            self::Online => 'Online',
        };
    }
}
