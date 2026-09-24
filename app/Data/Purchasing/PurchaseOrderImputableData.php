<?php

namespace App\Data\Purchasing;

use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use Spatie\LaravelData\Data;

class PurchaseOrderImputableData extends Data
{
    /**
     * @param  array<int, PurchaseOrderImputableItemData>  $items
     */
    public function __construct(
        public int $id,
        public string $order_number,
        public string $issue_date,
        public string $issue_date_formatted,
        public int $warehouse_id,
        public string $warehouse_name,
        public string $total_amount,
        public array $items,
    ) {}

    public static function fromModel(PurchaseOrder $order, SupplierVoucherType $type): self
    {
        $imputableItems = $order->items
            ->filter(fn (PurchaseOrderItem $item): bool => (float) $item->quantityPendingFor($type) > 0.0001)
            ->map(fn (PurchaseOrderItem $item): PurchaseOrderImputableItemData => PurchaseOrderImputableItemData::fromModel($item, $type))
            ->values()
            ->all();

        return new self(
            id: $order->id,
            order_number: $order->order_number,
            issue_date: $order->issue_date->toDateString(),
            issue_date_formatted: $order->issue_date->format('d/m/Y'),
            warehouse_id: $order->warehouse_id,
            warehouse_name: $order->warehouse->name,
            total_amount: (string) $order->total_amount,
            items: $imputableItems,
        );
    }
}
