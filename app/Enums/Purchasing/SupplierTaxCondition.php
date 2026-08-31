<?php

namespace App\Enums\Purchasing;

enum SupplierTaxCondition: string
{
    case ResponsibleInscripto = 'responsable_inscripto';
    case Monotributo = 'monotributo';
    case Exento = 'exento';
    case NoResponsable = 'no_responsable';
    case ConsumidorFinal = 'consumidor_final';
    case ExteriorNoCategorizado = 'exterior_no_categorizado';

    public function label(): string
    {
        return match ($this) {
            self::ResponsibleInscripto => 'IVA Responsable Inscripto',
            self::Monotributo => 'Responsable Monotributo',
            self::Exento => 'IVA Exento',
            self::NoResponsable => 'IVA No Responsable',
            self::ConsumidorFinal => 'Consumidor Final',
            self::ExteriorNoCategorizado => 'Proveedor del Exterior / No Categorizado',
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
