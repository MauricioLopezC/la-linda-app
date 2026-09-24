<?php

namespace App\Enums\Purchasing;

enum SupplierVoucherType: string
{
    case Invoice = 'factura';
    case CreditNote = 'nota_credito';
    case DebitNote = 'nota_debito';
    case Remito = 'remito';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Factura',
            self::CreditNote => 'Nota de crédito',
            self::DebitNote => 'Nota de débito',
            self::Remito => 'Remito',
        };
    }

    public function isInvoice(): bool
    {
        return $this === self::Invoice;
    }

    public function isCreditNote(): bool
    {
        return $this === self::CreditNote;
    }

    public function isRemito(): bool
    {
        return $this === self::Remito;
    }

    public function generatesStockMovement(): bool
    {
        return $this === self::Remito;
    }

    /**
     * Only invoices and remitos cover purchase order lines: the invoice bills them and the
     * remito receives them. Credit and debit notes adjust amounts, never ordered quantities.
     */
    public function canImputeToPurchaseOrder(): bool
    {
        return $this === self::Invoice || $this === self::Remito;
    }

    public function createsPayableBalance(): bool
    {
        return $this !== self::CreditNote && $this !== self::Remito;
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
