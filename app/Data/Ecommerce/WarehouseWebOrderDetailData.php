<?php

namespace App\Data\Ecommerce;

use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Inventory\Warehouse;
use Spatie\LaravelData\Data;

/**
 * Detailed web order data for picking sheet and warehouse fulfillment (EPIC-17).
 */
class WarehouseWebOrderDetailData extends Data
{
    /**
     * @param  array<int, WarehouseWebOrderItemData>  $items
     */
    public function __construct(
        public int $id,
        public int $number,
        public string $formatted_number,
        public string $customer_name,
        public ?string $customer_id_type,
        public ?string $customer_id_number,
        public ?string $customer_document,
        public ?string $customer_phone,
        public ?string $customer_email,
        public string $delivery_method,
        public string $delivery_method_label,
        public ?string $pickup_branch_name,
        public ?string $pickup_branch_address,
        public ?string $shipping_address,
        public ?string $shipping_notes,
        public ?string $notes,
        public string $status,
        public string $status_label,
        public string $items_amount,
        public string $formatted_items_amount,
        public string $shipping_cost,
        public string $formatted_shipping_cost,
        public string $total_amount,
        public string $formatted_total_amount,
        public string $placed_at_formatted,
        public ?string $paid_at_formatted,
        public ?string $paid_amount,
        public ?string $formatted_paid_amount,
        public ?string $mp_payment_id,
        public ?string $preparation_started_at_formatted,
        public ?string $prepared_by_name,
        public ?string $delivered_at_formatted,
        public ?string $delivered_by_name,
        public ?int $sale_id,
        public ?string $warehouse_name,
        public bool $can_start_preparation,
        public bool $can_deliver,
        public array $items,
    ) {}

    /**
     * Expects customer, pickupBranch, preparedBy, deliveredBy, sale, and items.article.unitOfMeasure loaded.
     */
    public static function fromModel(WebOrder $order, ?string $warehouseName = null): self
    {
        $customerDocument = null;
        if ($order->customer->id_number !== null && $order->customer->id_number !== '') {
            $customerDocument = "{$order->customer->id_type->label()} {$order->customer->id_number}";
        }

        $resolvedWarehouseName = $warehouseName
            ?? $order->sale?->pointOfSale?->warehouse->name
            ?? Warehouse::query()->where('is_online_channel', true)->where('is_active', true)->value('name');

        return new self(
            id: $order->id,
            number: $order->number,
            formatted_number: $order->formattedNumber(),
            customer_name: $order->customer->name,
            customer_id_type: $order->customer->id_type->label(),
            customer_id_number: $order->customer->id_number,
            customer_document: $customerDocument,
            customer_phone: $order->customer->phone,
            customer_email: $order->customer->email,
            delivery_method: $order->delivery_method->value,
            delivery_method_label: $order->delivery_method->label(),
            pickup_branch_name: $order->pickupBranch?->name,
            pickup_branch_address: $order->pickupBranch?->address,
            shipping_address: $order->shipping_address,
            shipping_notes: $order->shipping_notes,
            notes: $order->notes,
            status: $order->status->value,
            status_label: $order->statusLabel(),
            items_amount: (string) $order->items_amount,
            formatted_items_amount: '$ '.number_format((float) $order->items_amount, 2, ',', '.'),
            shipping_cost: (string) $order->shipping_cost,
            formatted_shipping_cost: '$ '.number_format((float) $order->shipping_cost, 2, ',', '.'),
            total_amount: (string) $order->total_amount,
            formatted_total_amount: '$ '.number_format((float) $order->total_amount, 2, ',', '.'),
            placed_at_formatted: $order->placed_at->format('d/m/Y H:i'),
            paid_at_formatted: $order->paid_at?->format('d/m/Y H:i'),
            paid_amount: $order->paid_amount !== null ? (string) $order->paid_amount : null,
            formatted_paid_amount: $order->paid_amount !== null ? '$ '.number_format((float) $order->paid_amount, 2, ',', '.') : null,
            mp_payment_id: $order->mp_payment_id,
            preparation_started_at_formatted: $order->preparation_started_at?->format('d/m/Y H:i'),
            prepared_by_name: $order->preparedBy?->name,
            delivered_at_formatted: $order->delivered_at?->format('d/m/Y H:i'),
            delivered_by_name: $order->deliveredBy?->name,
            sale_id: $order->sale?->id,
            warehouse_name: $resolvedWarehouseName,
            can_start_preparation: $order->status === WebOrderStatus::Paid,
            can_deliver: $order->status === WebOrderStatus::InPreparation,
            items: $order->items
                ->sortBy('id')
                ->map(fn (WebOrderItem $item): WarehouseWebOrderItemData => WarehouseWebOrderItemData::fromModel($item))
                ->values()
                ->all(),
        );
    }
}
