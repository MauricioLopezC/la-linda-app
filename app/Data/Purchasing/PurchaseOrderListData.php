<?php

namespace App\Data\Purchasing;

use App\Models\Purchasing\PurchaseOrder;
use Spatie\LaravelData\Data;

class PurchaseOrderListData extends Data
{
    public function __construct(
        public int $id,
        public string $order_number,
        public int $supplier_id,
        public string $supplier_name,
        public int $warehouse_id,
        public string $warehouse_name,
        public string $issue_date,
        public string $issue_date_formatted,
        public ?string $expected_delivery_date,
        public ?string $expected_delivery_date_formatted,
        public string $total_amount,
        public string $status,
        public string $status_label,
        public int $items_count,
        public bool $can_edit,
        public bool $can_issue,
        public bool $can_cancel,
    ) {}

    public static function fromModel(PurchaseOrder $order): self
    {
        return new self(
            id: $order->id,
            order_number: $order->order_number,
            supplier_id: $order->supplier_id,
            supplier_name: $order->supplier->business_name,
            warehouse_id: $order->warehouse_id,
            warehouse_name: $order->warehouse->name,
            issue_date: $order->issue_date->toDateString(),
            issue_date_formatted: $order->issue_date->format('d/m/Y'),
            expected_delivery_date: $order->expected_delivery_date?->toDateString(),
            expected_delivery_date_formatted: $order->expected_delivery_date?->format('d/m/Y'),
            total_amount: (string) $order->total_amount,
            status: $order->status->value,
            status_label: $order->status->label(),
            items_count: $order->items_count ?? $order->items()->count(),
            can_edit: $order->canBeEdited(),
            can_issue: $order->canBeIssued(),
            can_cancel: $order->canBeCancelled(),
        );
    }
}
