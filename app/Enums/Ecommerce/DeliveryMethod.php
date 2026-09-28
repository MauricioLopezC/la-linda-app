<?php

namespace App\Enums\Ecommerce;

enum DeliveryMethod: string
{
    case Pickup = 'retiro';
    case Shipping = 'envio';

    public function label(): string
    {
        return match ($this) {
            self::Pickup => 'Retiro en sucursal',
            self::Shipping => 'Envío a domicilio',
        };
    }
}
