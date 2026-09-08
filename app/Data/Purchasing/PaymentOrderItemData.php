<?php

namespace App\Data\Purchasing;

use App\Models\Purchasing\PaymentOrderItem;
use App\Models\Purchasing\SupplierVoucher;
use Spatie\LaravelData\Data;

/**
 * One line of the payment order response: the invoice that was paid and its new balance state.
 */
class PaymentOrderItemData extends Data
{
    public function __construct(
        public int $supplier_voucher_id,
        public string $amount_applied,
        /** Balance remaining on this invoice after the imputation. */
        public string $voucher_remaining_balance,
        /** SupplierVoucherStatus value after RecalculateVoucherBalanceStatus ran. */
        public string $voucher_status,
        public string $voucher_status_label,
    ) {}

    public static function fromModels(PaymentOrderItem $item, SupplierVoucher $voucher): self
    {
        return new self(
            supplier_voucher_id: $item->supplier_voucher_id,
            amount_applied: (string) $item->amount_applied,
            voucher_remaining_balance: $voucher->pendingBalance(),
            voucher_status: $voucher->status->value,
            voucher_status_label: $voucher->status->label(),
        );
    }
}
