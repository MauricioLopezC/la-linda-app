<?php

namespace App\Data\Purchasing;

use App\Models\Purchasing\SupplierVoucher;
use Spatie\LaravelData\Data;

class SupplierVoucherListData extends Data
{
    public function __construct(
        public int $id,
        public int $supplier_id,
        public string $supplier_business_name,
        public string $type,
        public string $type_label,
        public string $letter,
        public string $point_of_sale,
        public string $number,
        public string $formatted_number,
        public string $issue_date,
        public string $issue_date_formatted,
        public ?string $due_date,
        public ?string $due_date_formatted,
        public string $total_amount,
        public string $pending_balance,
        public string $status,
        public string $status_label,
        public bool $is_overdue,
    ) {}

    public static function fromModel(SupplierVoucher $voucher): self
    {
        return new self(
            id: $voucher->id,
            supplier_id: $voucher->supplier_id,
            supplier_business_name: $voucher->supplier->business_name,
            type: $voucher->type->value,
            type_label: $voucher->type->label(),
            letter: $voucher->letter->value,
            point_of_sale: $voucher->point_of_sale,
            number: $voucher->number,
            formatted_number: $voucher->letter->value.' '.$voucher->point_of_sale.'-'.$voucher->number,
            issue_date: $voucher->issue_date->toDateString(),
            issue_date_formatted: $voucher->issue_date->format('d/m/Y'),
            due_date: $voucher->due_date?->toDateString(),
            due_date_formatted: $voucher->due_date?->format('d/m/Y'),
            total_amount: $voucher->total_amount,
            pending_balance: $voucher->pendingBalance(),
            status: $voucher->status->value,
            status_label: $voucher->status->label(),
            is_overdue: $voucher->isOverdue(),
        );
    }
}
