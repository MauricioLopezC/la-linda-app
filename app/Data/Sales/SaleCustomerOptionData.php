<?php

namespace App\Data\Sales;

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
    ) {}

    public static function fromModel(Customer $customer): self
    {
        return new self(
            id: $customer->id,
            name: $customer->name,
            id_number: $customer->formattedIdNumber(),
            price_list_name: $customer->priceList?->name,
            is_default: $customer->is_default,
        );
    }
}
