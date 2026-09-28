<?php

namespace App\Enums\Sales;

/**
 * How a payment method is collected and counted at the cash closing (EPIC-04 / HU-060).
 */
enum PaymentMethodKind: string
{
    case Cash = 'efectivo';
    case Card = 'tarjeta';
    case VirtualWallet = 'billetera_virtual';
    case Transfer = 'transferencia';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Card => 'Tarjeta',
            self::VirtualWallet => 'Billetera virtual',
            self::Transfer => 'Transferencia',
            self::Other => 'Otro',
        };
    }

    /**
     * Only cash hands back change and is counted bill by bill.
     */
    public function givesChange(): bool
    {
        return $this === self::Cash;
    }

    /**
     * Cards are declared at closing with the POSNET batch number.
     */
    public function requiresBatchReference(): bool
    {
        return $this === self::Card;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function toOptions(): array
    {
        return array_map(
            fn (self $kind): array => [
                'value' => $kind->value,
                'label' => $kind->label(),
            ],
            self::cases()
        );
    }
}
