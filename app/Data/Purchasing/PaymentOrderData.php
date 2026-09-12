<?php

namespace App\Data\Purchasing;

use App\Models\Purchasing\PaymentOrder;
use Spatie\LaravelData\Data;

/**
 * Payment order response (HU-027).
 *
 * @property array<int, PaymentOrderItemData> $items
 * @property array<int, PaymentOrderMethodData> $methods
 */
class PaymentOrderData extends Data
{
    /**
     * @param  array<int, PaymentOrderItemData>  $items
     * @param  array<int, PaymentOrderMethodData>  $methods
     */
    public function __construct(
        public int $id,
        public string $order_number,
        public int $supplier_id,
        public string $supplier_name,
        public string $date,
        public string $total_amount,
        public string $status,
        public string $status_label,
        public ?string $notes,
        public ?string $created_at,
        public array $items,
        public array $methods,
    ) {}

    /**
     * The order must already have loaded: supplier, paymentMethods.
     */
    public static function fromModel(PaymentOrder $order, PaymentOrderItemData ...$itemData): self
    {
        $methods = $order->paymentMethods->map(
            fn ($m) => PaymentOrderMethodData::fromModel($m)
        )->all();

        return new self(
            id: $order->id,
            order_number: $order->order_number,
            supplier_id: $order->supplier_id,
            supplier_name: $order->supplier->business_name,
            date: $order->date->toDateString(),
            total_amount: (string) $order->total_amount,
            status: $order->status->value,
            status_label: $order->status->label(),
            notes: $order->notes,
            created_at: $order->created_at?->toIso8601String(),
            items: array_values($itemData),
            methods: $methods,
        );
    }
}
