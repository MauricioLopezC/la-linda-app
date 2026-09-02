<?php

namespace App\Actions\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use InvalidArgumentException;

class ResolveSupplierVoucherStatus
{
    use ConvertsMoneyToCents;

    public function handle(
        SupplierVoucherType $type,
        string $totalAmount,
        string $pendingBalance,
    ): SupplierVoucherStatus {
        $totalCents = $this->moneyToCents($totalAmount);
        $pendingCents = $this->moneyToCents($pendingBalance);

        if ($totalCents <= 0) {
            throw new InvalidArgumentException('El importe total debe ser mayor a cero.');
        }

        if ($type->isInvoice()) {
            return match (true) {
                $pendingCents <= 0 => SupplierVoucherStatus::Paid,
                $pendingCents < $totalCents => SupplierVoucherStatus::PartiallyPaid,
                default => SupplierVoucherStatus::Pending,
            };
        }

        return match (true) {
            $pendingCents <= 0 => SupplierVoucherStatus::Applied,
            $pendingCents < $totalCents => SupplierVoucherStatus::PartiallyApplied,
            default => SupplierVoucherStatus::PendingApplication,
        };
    }
}
