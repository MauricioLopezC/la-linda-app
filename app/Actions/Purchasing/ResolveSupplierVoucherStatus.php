<?php

namespace App\Actions\Purchasing;

use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ResolveSupplierVoucherStatus
{
    public function handle(
        SupplierVoucherType $type,
        string $totalAmount,
        string $pendingBalance,
    ): SupplierVoucherStatus {
        $totalCents = $this->toCents($totalAmount);
        $pendingCents = $this->toCents($pendingBalance);

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

    private function toCents(string $amount): int
    {
        $normalized = Str::of($amount)->trim()->replace(',', '.')->toString();

        if (preg_match('/^-?\d+(?:\.\d{1,2})?$/', $normalized) !== 1) {
            throw new InvalidArgumentException("El importe '{$amount}' no tiene un formato monetario válido.");
        }

        $isNegative = str_starts_with($normalized, '-');
        $unsignedAmount = ltrim($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', $unsignedAmount, 2), 2, '');
        $cents = ((int) $whole * 100) + (int) Str::padRight($fraction, 2, '0');

        return $isNegative ? -$cents : $cents;
    }
}
