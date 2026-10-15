<?php

namespace App\Data\Ecommerce;

use App\Enums\Ecommerce\DeliveryMethod;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Ecommerce\WebOrder;
use Spatie\LaravelData\Data;

/**
 * Summary row of a web order for the warehouse operations inbox (EPIC-17).
 */
class WarehouseWebOrderListData extends Data
{
    public function __construct(
        public int $id,
        public int $number,
        public string $formatted_number,
        public string $customer_name,
        public ?string $customer_document,
        public string $delivery_method,
        public string $delivery_method_label,
        public string $destination,
        public string $status,
        public string $status_label,
        public int $items_count,
        public string $total_amount,
        public string $formatted_total_amount,
        public string $placed_at_formatted,
        public ?string $paid_at_formatted,
        public ?string $preparation_started_at_formatted,
        public ?string $prepared_by_name,
        public ?string $delivered_at_formatted,
        public ?string $delivered_by_name,
        public ?int $sale_id,
        public bool $can_start_preparation,
        public bool $can_deliver,
    ) {}

    /**
     * Expects customer, pickupBranch, preparedBy, deliveredBy, and items_count (or items) loaded.
     */
    public static function fromModel(WebOrder $order): self
    {
        $itemsCount = $order->relationLoaded('items')
            ? $order->items->count()
            : (int) ($order->getAttribute('items_count') ?? 0);

        $destination = $order->delivery_method === DeliveryMethod::Pickup
            ? ($order->pickupBranch !== null ? $order->pickupBranch->name : 'Retiro en sucursal')
            : ($order->shipping_address ?? 'Envío a domicilio');

        $customerDocument = null;
        if ($order->customer->id_number !== null && $order->customer->id_number !== '') {
            $customerDocument = "{$order->customer->id_type->label()} {$order->customer->id_number}";
        }

        return new self(
            id: $order->id,
            number: $order->number,
            formatted_number: $order->formattedNumber(),
            customer_name: $order->customer->name,
            customer_document: $customerDocument,
            delivery_method: $order->delivery_method->value,
            delivery_method_label: $order->delivery_method->label(),
            destination: $destination,
            status: $order->status->value,
            status_label: $order->statusLabel(),
            items_count: $itemsCount,
            total_amount: (string) $order->total_amount,
            formatted_total_amount: '$ '.number_format((float) $order->total_amount, 2, ',', '.'),
            placed_at_formatted: $order->placed_at->format('d/m/Y H:i'),
            paid_at_formatted: $order->paid_at?->format('d/m/Y H:i'),
            preparation_started_at_formatted: $order->preparation_started_at?->format('d/m/Y H:i'),
            prepared_by_name: $order->preparedBy?->name,
            delivered_at_formatted: $order->delivered_at?->format('d/m/Y H:i'),
            delivered_by_name: $order->deliveredBy?->name,
            sale_id: $order->sale?->id,
            can_start_preparation: $order->status === WebOrderStatus::Paid,
            can_deliver: $order->status === WebOrderStatus::InPreparation,
        );
    }
}
