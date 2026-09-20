<?php

namespace App\Enums\Customers;

enum PersonType: string
{
    case Fisica = 'fisica';
    case Juridica = 'juridica';

    public function label(): string
    {
        return match ($this) {
            self::Fisica => 'Persona Física',
            self::Juridica => 'Persona Jurídica',
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
