<?php

namespace App\Data\Purchasing;

use App\Models\Purchasing\SupplierVoucher;
use Spatie\LaravelData\Data;

/**
 * An invoice a credit note can still be imputed to while it is being registered (HU-054):
 * same supplier, not annulled, with a pending balance greater than zero.
 */
class AssociableInvoiceOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $formatted_number,
        public string $issue_date_formatted,
        public ?string $due_date_formatted,
        public string $total_amount,
        public string $outstanding_amount,
    ) {}

    public static function fromModel(SupplierVoucher $invoice): self
    {
        return new self(
            id: $invoice->id,
            formatted_number: $invoice->letter->value.' '.$invoice->point_of_sale.'-'.$invoice->number,
            issue_date_formatted: $invoice->issue_date->format('d/m/Y'),
            due_date_formatted: $invoice->due_date?->format('d/m/Y'),
            total_amount: (string) $invoice->total_amount,
            outstanding_amount: $invoice->outstandingAmount(),
        );
    }
}
