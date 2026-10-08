<?php

namespace App\Data\Ecommerce;

use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use Spatie\LaravelData\Data;

/**
 * Read-only detail of an online order for its customer.
 */
class WebOrderData extends Data
{
    /**
     * @param  array<int, WebOrderItemData>  $items
     */
    public function __construct(
        public int $id,
        public int $number,
        public string $formatted_number,
        public string $placed_at_formatted,
        public string $status,
        public string $status_label,
        public string $delivery_method,
        public string $delivery_method_label,
        public ?string $pickup_branch_name,
        public ?string $pickup_branch_address,
        public ?string $shipping_address,
        public ?string $shipping_notes,
        public ?string $notes,
        public string $items_amount,
        public string $formatted_items_amount,
        public string $shipping_cost,
        public string $formatted_shipping_cost,
        public string $total_amount,
        public string $formatted_total_amount,
        public array $items,
    ) {}

    /**
     * Expects pickupBranch, items.article.unitOfMeasure and items.priceList loaded.
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
            delivery_method: $order->delivery_method->value,
            delivery_method_label: $order->delivery_method->label(),
            pickup_branch_name: $order->pickupBranch?->name,
            pickup_branch_address: $order->pickupBranch?->address,
            shipping_address: $order->shipping_address,
            shipping_notes: $order->shipping_notes,
            notes: $order->notes,
            items_amount: $order->items_amount,
            formatted_items_amount: self::money($order->items_amount),
            shipping_cost: $order->shipping_cost,
            formatted_shipping_cost: self::money($order->shipping_cost),
            total_amount: $order->total_amount,
            formatted_total_amount: self::money($order->total_amount),
            items: $order->items
                ->sortBy('id')
                ->map(fn (WebOrderItem $item): WebOrderItemData => WebOrderItemData::fromModel($item))
                ->values()
                ->all(),
        );
    }

    private static function money(string $amount): string
    {
        return '$ '.number_format((float) $amount, 2, ',', '.');
    }
}
