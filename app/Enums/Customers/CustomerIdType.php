<?php

namespace App\Enums\Customers;

enum CustomerIdType: string
{
    case Cuit = 'cuit';
    case Dni = 'dni';
    case SinIdentificar = 'sin_identificar';

    public function label(): string
    {
        return match ($this) {
            self::Cuit => 'CUIT',
            self::Dni => 'DNI',
            self::SinIdentificar => 'Sin identificar',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function toOptions(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases()
        );
    }
}
