<?php

namespace App\Data\Purchasing;

use App\Models\Purchasing\Supplier;
use App\Rules\Purchasing\ValidCuit;
use Spatie\LaravelData\Data;

class SupplierData extends Data
{
    public function __construct(
        public int $id,
        public string $business_name,
        public string $tax_id,
        public string $tax_id_raw,
        public string $tax_condition,
        public string $tax_condition_label,
        public ?string $address,
        public ?string $rubro,
        public ?string $bank_account,
        public ?string $commercial_terms,
        public bool $is_active,
        public bool $has_associated_records,
        public ?string $created_at,
    ) {}

    public static function fromModel(Supplier $supplier): self
    {
        return new self(
            id: $supplier->id,
            business_name: $supplier->business_name,
            tax_id: ValidCuit::format($supplier->tax_id) ?? $supplier->tax_id,
            tax_id_raw: $supplier->tax_id,
            tax_condition: $supplier->tax_condition->value,
            tax_condition_label: $supplier->tax_condition->label(),
            address: $supplier->address,
            rubro: $supplier->rubro,
            bank_account: $supplier->bank_account,
            commercial_terms: $supplier->commercial_terms,
            is_active: $supplier->is_active,
            has_associated_records: $supplier->hasAssociatedRecords(),
            created_at: $supplier->created_at?->toIso8601String(),
        );
    }
}
