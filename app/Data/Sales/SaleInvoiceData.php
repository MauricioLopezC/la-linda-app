<?php

namespace App\Data\Sales;

use App\Models\Sales\Invoice;
use Spatie\LaravelData\Data;

class SaleInvoiceData extends Data
{
    public function __construct(
        public int $id,
        public int $point_of_sale_id,
        public int $point_of_sale_number,
        public string $type,
        public string $type_label,
        public int $number,
        public string $formatted_number,
        public string $voucher_label,
        public string $issued_at,
        public string $issued_at_formatted,
        public string $customer_name,
        public string $customer_tax_condition,
        public ?string $customer_id_type,
        public ?string $customer_id_number,
        public ?string $customer_address,
        public string $net_amount,
        public string $vat_amount,
        public string $total_amount,
        public ?string $user_name,
    ) {}

    public static function fromModel(Invoice $invoice): self
    {
        $invoice->loadMissing(['pointOfSale', 'user']);

        return new self(
            id: $invoice->id,
            point_of_sale_id: $invoice->point_of_sale_id,
            point_of_sale_number: $invoice->point_of_sale_number,
            type: $invoice->type->value,
            type_label: $invoice->type->label(),
            number: $invoice->number,
            formatted_number: $invoice->formattedNumber(),
            voucher_label: $invoice->voucherLabel(),
            issued_at: $invoice->issued_at->toIso8601String(),
            issued_at_formatted: $invoice->issued_at->format('d/m/Y H:i'),
            customer_name: $invoice->customer_name,
            customer_tax_condition: $invoice->customer_tax_condition,
            customer_id_type: $invoice->customer_id_type,
            customer_id_number: $invoice->customer_id_number,
            customer_address: $invoice->customer_address,
            net_amount: $invoice->net_amount,
            vat_amount: $invoice->vat_amount,
            total_amount: $invoice->total_amount,
            user_name: $invoice->user->name,
        );
    }
}
