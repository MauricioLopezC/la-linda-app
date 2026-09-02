<?php

namespace App\Enums\Purchasing;

enum PaymentOrderStatus: string
{
    case Issued = 'emitida';
    case Cancelled = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Emitida',
            self::Cancelled => 'Anulada',
        };
    }
}
