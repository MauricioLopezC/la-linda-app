<?php

namespace App\Enums\Customers;

enum CustomerTaxCondition: string
{
    case ResponsibleInscripto = 'responsable_inscripto';
    case Monotributo = 'monotributo';
    case ConsumidorFinal = 'consumidor_final';
    case Exento = 'exento';

    public function label(): string
    {
        return match ($this) {
            self::ResponsibleInscripto => 'IVA Responsable Inscripto',
            self::Monotributo => 'Responsable Monotributo',
            self::ConsumidorFinal => 'Consumidor Final',
            self::Exento => 'IVA Exento',
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
