<?php

namespace App\Enums\Ecommerce;

/**
 * State of an online order (HU-062 / HU-050 / EPIC-17).
 */
enum WebOrderStatus: string
{
    case Pending = 'pendiente';
    case Paid = 'pagado';
    case InPreparation = 'en_preparacion';
    case Delivered = 'entregado';

    public function label(?DeliveryMethod $deliveryMethod = null): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de pago',
            self::Paid => 'Pagado',
            self::InPreparation => 'En preparación',
            self::Delivered => $deliveryMethod === DeliveryMethod::Shipping ? 'Despachado' : 'Entregado',
        };
    }
}
