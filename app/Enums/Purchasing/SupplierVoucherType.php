<?php

namespace App\Enums\Purchasing;

enum SupplierVoucherType: string
{
    case Invoice = 'factura';
    case CreditNote = 'nota_credito';
    case DebitNote = 'nota_debito';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Factura',
            self::CreditNote => 'Nota de crédito',
            self::DebitNote => 'Nota de débito',
        };
    }

    public function isInvoice(): bool
    {
        return $this === self::Invoice;
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function toOptions(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases()
        );
    }
}
