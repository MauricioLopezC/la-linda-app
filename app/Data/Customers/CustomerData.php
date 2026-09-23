<?php

namespace App\Data\Customers;

use App\Models\Customers\Customer;
use Spatie\LaravelData\Data;

class CustomerData extends Data
{
    public function __construct(
        public int $id,
        public string $person_type,
        public string $person_type_label,
        public string $name,
        public string $id_type,
        public string $id_type_label,
        public ?string $id_number,
        public ?string $id_number_raw,
        public string $tax_condition,
        public string $tax_condition_label,
        public ?int $price_list_id,
        public ?string $price_list_name,
        public ?string $address,
        public ?string $phone,
        public ?string $email,
        public bool $is_active,
        public bool $is_default,
        public bool $has_associated_records,
        public ?string $created_at,
    ) {}

    public static function fromModel(Customer $customer): self
    {
        return new self(
            id: $customer->id,
            person_type: $customer->person_type->value,
            person_type_label: $customer->person_type->label(),
            name: $customer->name,
            id_type: $customer->id_type->value,
            id_type_label: $customer->id_type->label(),
            id_number: $customer->formattedIdNumber(),
            id_number_raw: $customer->id_number,
            tax_condition: $customer->tax_condition->value,
            tax_condition_label: $customer->tax_condition->label(),
            price_list_id: $customer->price_list_id,
            price_list_name: $customer->relationLoaded('priceList') ? $customer->priceList?->name : null,
            address: $customer->address,
            phone: $customer->phone,
            email: $customer->email,
            is_active: $customer->is_active,
            is_default: $customer->is_default,
            has_associated_records: $customer->hasAssociatedRecords(),
            created_at: $customer->created_at?->toIso8601String(),
        );
    }
}
