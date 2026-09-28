<?php

namespace App\Enums\Sales;

/**
 * Bills counted when a cash session opens and closes (HU-057 / HU-060). Coins are not accepted
 * (PO, 28/09/2026), so the count is bills only.
 */
enum CashDenomination: int
{
    case TwentyThousand = 20000;
    case TenThousand = 10000;
    case TwoThousand = 2000;
    case OneThousand = 1000;
    case FiveHundred = 500;
    case TwoHundred = 200;
    case OneHundred = 100;
    case Fifty = 50;
    case Twenty = 20;
    case Ten = 10;

    public function label(): string
    {
        return '$'.number_format($this->value, 0, ',', '.');
    }
}
