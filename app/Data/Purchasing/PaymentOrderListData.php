<?php

namespace App\Data\Purchasing;

use App\Models\Purchasing\PaymentOrder;
use Spatie\LaravelData\Data;

class PaymentOrderListData extends Data
{
    /**
     * @param  array<int, PaymentOrderListItemData>  $items
     * @param  array<int, PaymentOrderMethodData>  $methods
     */
    public function __construct(
        public int $id,
        public string $order_number,
        public int $supplier_id,
        public string $supplier_name,
        public string $date,
        public string $date_formatted,
        public string $total_amount,
        public string $status,
        public string $status_label,
        public string $payment_methods_summary,
        public array $items,
        public array $methods,
    ) {}

    public static function fromModel(PaymentOrder $order): self
    {
        $items = $order->items->map(
            fn ($item) => PaymentOrderListItemData::fromModel($item)
        )->values()->all();

        $methods = $order->paymentMethods->map(
            fn ($m) => PaymentOrderMethodData::fromModel($m)
        )->values()->all();

        $methodsSummary = $order->paymentMethods
            ->map(fn ($m) => $m->paymentMethod->name)
            ->filter()
            ->unique()
            ->join(', ');

        return new self(
            id: $order->id,
            order_number: $order->order_number,
            supplier_id: $order->supplier_id,
            supplier_name: $order->supplier->business_name,
            date: $order->date->toDateString(),
            date_formatted: $order->date->format('d/m/Y'),
            total_amount: (string) $order->total_amount,
            status: $order->status->value,
            status_label: $order->status->label(),
            payment_methods_summary: $methodsSummary ?: '—',
            items: $items,
            methods: $methods,
        );
    }
}
