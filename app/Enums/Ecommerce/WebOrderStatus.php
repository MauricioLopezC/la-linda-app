<?php

namespace App\Enums\Ecommerce;

/**
 * State of an online order. EPIC-16 / EPIC-17 add the preparation and delivery states.
 */
enum WebOrderStatus: string
{
    case Pending = 'pendiente';
    case Paid = 'pagado';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de pago',
            self::Paid => 'Pagado',
        };
    }
}
