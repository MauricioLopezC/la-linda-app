<?php

namespace App\Actions\Purchasing;

use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Models\Purchasing\SupplierVoucher;

/**
 * Re-derive a voucher's status from its live imputations and persist it.
 *
 * Shared by HU-054 (credit/debit note applications) and HU-027 (payment orders): each calls this
 * for every voucher it touched, inside its own transaction, after inserting the imputation rows.
 * The status is never set by hand.
 */
class RecalculateVoucherBalanceStatus
{
    public function __construct(private ResolveSupplierVoucherStatus $resolveStatus) {}

    public function handle(SupplierVoucher $voucher): SupplierVoucher
    {
        if ($voucher->status === SupplierVoucherStatus::Cancelled) {
            // 'anulada' is terminal; it is only left by a counter-entry, not by recalculation.
            return $voucher;
        }

        $status = $this->resolveStatus->handle(
            $voucher->type,
            $voucher->total_amount,
            $voucher->outstandingAmount(),
        );

        if ($status !== $voucher->status) {
            $voucher->update(['status' => $status]);
        }

        return $voucher;
    }
}
