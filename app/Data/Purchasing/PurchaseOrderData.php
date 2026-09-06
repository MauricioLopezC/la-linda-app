<?php

namespace App\Data\Purchasing;

use App\Models\Purchasing\PurchaseOrder;
use App\Rules\Purchasing\ValidCuit;
use Spatie\LaravelData\Data;

class PurchaseOrderData extends Data
{
    /**
     * @param  array<int, PurchaseOrderItemData>  $items
     */
    public function __construct(
        public int $id,
        public string $order_number,
        public int $supplier_id,
        public string $supplier_name,
        public string $supplier_tax_id,
        public int $warehouse_id,
        public string $warehouse_name,
        public ?string $payment_terms,
        public string $issue_date,
        public string $issue_date_formatted,
        public ?string $expected_delivery_date,
        public ?string $expected_delivery_date_formatted,
        public string $total_amount,
        public string $status,
        public string $status_label,
        public ?string $notes,
        public ?string $created_at,
        public ?string $user_name,
        public ?string $cancelled_at,
        public ?string $cancelled_by_name,
        public ?string $cancellation_reason,
        public bool $can_edit,
        public bool $can_issue,
        public bool $can_cancel,
        public array $items,
    ) {}

    public static function fromModel(PurchaseOrder $order): self
    {
        $order->loadMissing(['supplier', 'warehouse', 'items.article.unitOfMeasure', 'user', 'cancelledByUser']);

        return new self(
            id: $order->id,
            order_number: $order->order_number,
            supplier_id: $order->supplier_id,
            supplier_name: $order->supplier->business_name,
            supplier_tax_id: ValidCuit::format($order->supplier->tax_id) ?? $order->supplier->tax_id,
            warehouse_id: $order->warehouse_id,
            warehouse_name: $order->warehouse->name,
            payment_terms: $order->payment_terms,
            issue_date: $order->issue_date->toDateString(),
            issue_date_formatted: $order->issue_date->format('d/m/Y'),
            expected_delivery_date: $order->expected_delivery_date?->toDateString(),
            expected_delivery_date_formatted: $order->expected_delivery_date?->format('d/m/Y'),
            total_amount: (string) $order->total_amount,
            status: $order->status->value,
            status_label: $order->status->label(),
            notes: $order->notes,
            created_at: $order->created_at?->toIso8601String(),
            user_name: $order->user?->name,
            cancelled_at: $order->cancelled_at?->format('d/m/Y H:i'),
            cancelled_by_name: $order->cancelledByUser?->name,
            cancellation_reason: $order->cancellation_reason,
            can_edit: $order->canBeEdited(),
            can_issue: $order->canBeIssued(),
            can_cancel: $order->canBeCancelled(),
            items: PurchaseOrderItemData::collect($order->items)->all(),
        );
    }
}
