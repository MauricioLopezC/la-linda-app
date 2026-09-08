<?php

namespace App\Data\Purchasing;

use App\Models\Purchasing\PaymentOrder;
use Spatie\LaravelData\Data;

/**
 * Payment order response (HU-027).
 *
 * @property array<int, PaymentOrderItemData> $items
 */
class PaymentOrderData extends Data
{
    /**
     * @param  array<int, PaymentOrderItemData>  $items
     */
    public function __construct(
        public int $id,
        public string $order_number,
        public int $supplier_id,
        public string $supplier_name,
        public int $payment_method_id,
        public string $payment_method_name,
        public string $date,
        public string $total_amount,
        public string $status,
        public string $status_label,
        public ?string $notes,
        public ?string $created_at,
        public array $items,
    ) {}

    /**
     * The order must already have loaded: supplier, paymentMethod, items.
     */
    public static function fromModel(PaymentOrder $order, PaymentOrderItemData ...$itemData): self
    {
        return new self(
            id: $order->id,
            order_number: $order->order_number,
            supplier_id: $order->supplier_id,
            supplier_name: $order->supplier->business_name,
            payment_method_id: $order->payment_method_id,
            payment_method_name: $order->paymentMethod->name,
            date: $order->date->toDateString(),
            total_amount: (string) $order->total_amount,
            status: $order->status->value,
            status_label: $order->status->label(),
            notes: $order->notes,
            created_at: $order->created_at?->toIso8601String(),
            items: array_values($itemData),
        );
    }
}
