<?php

namespace App\Enums\Sales;

use App\Enums\Pricing\PriceListChannel;

/**
 * Channel a sale is made through. `Online` is reserved for e-commerce orders (EPIC-15):
 * the counter sale screen always opens sales as `Mostrador`.
 */
enum SaleChannel: string
{
    case Mostrador = 'mostrador';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::Mostrador => 'Mostrador',
            self::Online => 'Online',
        };
    }

    /**
     * The price list channel whose list applies to this sale channel (HU-056 cascade, step 2).
     *
     * @return PriceListChannel::Mostrador|PriceListChannel::Online
     */
    public function toPriceListChannel(): PriceListChannel
    {
        return match ($this) {
            self::Mostrador => PriceListChannel::Mostrador,
            self::Online => PriceListChannel::Online,
        };
    }
}
