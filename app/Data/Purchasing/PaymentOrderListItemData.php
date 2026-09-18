<?php

namespace App\Data\Purchasing;

use App\Models\Purchasing\PaymentOrderItem;
use Spatie\LaravelData\Data;

class PaymentOrderListItemData extends Data
{
    public function __construct(
        public int $id,
        public int $supplier_voucher_id,
        public string $voucher_type,
        public string $voucher_type_label,
        public string $voucher_number,
        public string $amount_applied,
    ) {}

    public static function fromModel(PaymentOrderItem $item): self
    {
        $voucher = $item->voucher;

        return new self(
            id: $item->id,
            supplier_voucher_id: $item->supplier_voucher_id,
            voucher_type: $voucher->type->value,
            voucher_type_label: $voucher->type->label(),
            voucher_number: $voucher->letter->value.' '.$voucher->point_of_sale.'-'.$voucher->number,
            amount_applied: (string) $item->amount_applied,
        );
    }
}
