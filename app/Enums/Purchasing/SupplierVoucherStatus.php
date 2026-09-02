<?php

namespace App\Enums\Purchasing;

enum SupplierVoucherStatus: string
{
    case Pending = 'pendiente';
    case PartiallyPaid = 'pagada_parcial';
    case Paid = 'pagada';
    case PendingApplication = 'pendiente_imputar';
    case PartiallyApplied = 'imputada_parcial';
    case Applied = 'imputada';
    case Cancelled = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::PartiallyPaid => 'Pagada parcialmente',
            self::Paid => 'Pagada',
            self::PendingApplication => 'Pendiente de imputar',
            self::PartiallyApplied => 'Imputada parcialmente',
            self::Applied => 'Imputada',
            self::Cancelled => 'Anulada',
        };
    }
}
