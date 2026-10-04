<?php

namespace App\Data\Sales;

use App\Enums\Sales\InvoiceType;
use App\Models\Customers\Customer;
use Spatie\LaravelData\Data;

class SaleCustomerOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $id_number,
        public ?string $price_list_name,
        public bool $is_default,
        public string $tax_condition,
        public string $tax_condition_label,
        public string $id_type,
        public string $id_type_label,
        public string $invoice_type,
        public string $invoice_type_label,
    ) {}

    public static function fromModel(Customer $customer): self
    {
        $invoiceType = InvoiceType::forTaxCondition($customer->tax_condition);

        return new self(
            id: $customer->id,
            name: $customer->name,
            id_number: $customer->formattedIdNumber(),
            price_list_name: $customer->priceList?->name,
            is_default: $customer->is_default,
            tax_condition: $customer->tax_condition->value,
            tax_condition_label: $customer->tax_condition->label(),
            id_type: $customer->id_type->value,
            id_type_label: $customer->id_type->label(),
            invoice_type: $invoiceType->value,
            invoice_type_label: $invoiceType->label(),
        );
    }
}
