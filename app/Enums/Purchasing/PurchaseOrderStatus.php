<?php

namespace App\Enums\Purchasing;

enum PurchaseOrderStatus: string
{
    case Draft = 'borrador';
    case Issued = 'emitida';
    case Cancelled = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Issued => 'Emitida',
            self::Cancelled => 'Cancelada',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function toOptions(): array
    {
        return array_map(
            fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            self::cases()
        );
    }
}
