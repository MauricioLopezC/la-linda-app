<?php

namespace App\Data\Ecommerce;

use App\Models\Ecommerce\WebOrder;
use Spatie\LaravelData\Data;

/**
 * One row of the customer's "Mis pedidos" list.
 */
class WebOrderListData extends Data
{
    public function __construct(
        public int $id,
        public int $number,
        public string $formatted_number,
        public string $placed_at_formatted,
        public string $status,
        public string $status_label,
        public string $delivery_method_label,
        public int $items_count,
        public string $total_amount,
        public string $formatted_total_amount,
    ) {}

    /**
     * Expects items_count loaded.
     */
    public static function fromModel(WebOrder $order): self
    {
        return new self(
            id: $order->id,
            number: $order->number,
            formatted_number: $order->formattedNumber(),
            placed_at_formatted: $order->placed_at->format('d/m/Y H:i'),
            status: $order->status->value,
            status_label: $order->status->label(),
            delivery_method_label: $order->delivery_method->label(),
            items_count: (int) $order->getAttribute('items_count'),
            total_amount: $order->total_amount,
            formatted_total_amount: '$ '.number_format((float) $order->total_amount, 2, ',', '.'),
        );
    }
}
