<?php

namespace App\Enums\Sales;

/**
 * Kind of money movement in a cash session. The stored amount is always positive; the type
 * decides whether it adds to or takes from the session (HU-059 adds the loan types).
 */
enum CashMovementType: string
{
    case Opening = 'apertura';
    case Sale = 'venta';
    case Income = 'ingreso';
    case Expense = 'egreso';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Apertura',
            self::Sale => 'Venta',
            self::Income => 'Ingreso',
            self::Expense => 'Egreso',
        };
    }

    /**
     * @return 1|-1
     */
    public function sign(): int
    {
        return match ($this) {
            self::Opening, self::Sale, self::Income => 1,
            self::Expense => -1,
        };
    }

    public function requiresReason(): bool
    {
        return in_array($this, [self::Income, self::Expense], true);
    }
}
