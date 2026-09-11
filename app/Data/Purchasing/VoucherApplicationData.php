<?php

namespace App\Data\Purchasing;

use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\VoucherApplication;
use Spatie\LaravelData\Data;

/**
 * One credit-note-to-invoice imputation (HU-054), shown read-only on both vouchers' detail views.
 *
 * `direction` is relative to the voucher being viewed: `made` when it is the credit note that was
 * applied, `received` when it is the invoice that took the credit.
 */
class VoucherApplicationData extends Data
{
    public function __construct(
        public int $id,
        public string $direction,
        public int $counterparty_id,
        public string $counterparty_type_label,
        public string $counterparty_formatted_number,
        public string $amount,
        public string $user_name,
        public ?string $created_at_formatted,
    ) {}

    public static function madeByCreditNote(VoucherApplication $application): self
    {
        return self::forCounterparty($application, 'made', $application->targetVoucher);
    }

    public static function receivedByInvoice(VoucherApplication $application): self
    {
        return self::forCounterparty($application, 'received', $application->sourceVoucher);
    }

    private static function forCounterparty(
        VoucherApplication $application,
        string $direction,
        SupplierVoucher $counterparty,
    ): self {
        return new self(
            id: $application->id,
            direction: $direction,
            counterparty_id: $counterparty->id,
            counterparty_type_label: $counterparty->type->label(),
            counterparty_formatted_number: $counterparty->letter->value.' '
                .$counterparty->point_of_sale.'-'.$counterparty->number,
            amount: (string) $application->amount,
            user_name: $application->user->name,
            created_at_formatted: $application->created_at?->format('d/m/Y H:i'),
        );
    }
}
