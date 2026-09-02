<?php

namespace App\Data\Purchasing;

use App\Models\Purchasing\Supplier;
use App\Rules\Purchasing\ValidCuit;
use Spatie\LaravelData\Data;

class SupplierOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $business_name,
        public string $tax_id,
    ) {}

    public static function fromModel(Supplier $supplier): self
    {
        return new self(
            id: $supplier->id,
            business_name: $supplier->business_name,
            tax_id: ValidCuit::format($supplier->tax_id) ?? $supplier->tax_id,
        );
    }
}
