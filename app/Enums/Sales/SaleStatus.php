<?php

namespace App\Enums\Sales;

enum SaleStatus: string
{
    case Open = 'abierta';
    case Discarded = 'descartada';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierta',
            self::Discarded => 'Descartada',
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
