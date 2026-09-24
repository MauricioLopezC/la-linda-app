<?php

namespace App\Data\Sales;

use App\Models\Sales\Sale;
use Spatie\LaravelData\Data;

class SaleData extends Data
{
    public function __construct(
        public int $id,
        public int $point_of_sale_id,
        public int $point_of_sale_number,
        public string $branch_name,
        public string $warehouse_name,
        public string $channel,
        public string $channel_label,
        public int $customer_id,
        public string $customer_name,
        public ?string $customer_price_list_name,
        public ?string $user_name,
        public string $opened_at,
        public string $opened_at_formatted,
        public string $status,
        public string $status_label,
        public bool $is_open,
        public string $total_amount,
    ) {}

    public static function fromModel(Sale $sale): self
    {
        $sale->loadMissing([
            'pointOfSale.warehouse.branch',
            'customer.priceList',
            'user',
        ]);

        return new self(
            id: $sale->id,
            point_of_sale_id: $sale->point_of_sale_id,
            point_of_sale_number: $sale->pointOfSale->number,
            branch_name: $sale->pointOfSale->warehouse->branch->name,
            warehouse_name: $sale->pointOfSale->warehouse->name,
            channel: $sale->channel->value,
            channel_label: $sale->channel->label(),
            customer_id: $sale->customer_id,
            customer_name: $sale->customer->name,
            customer_price_list_name: $sale->customer->priceList?->name,
            user_name: $sale->user?->name,
            opened_at: $sale->opened_at->toIso8601String(),
            opened_at_formatted: $sale->opened_at->format('d/m/Y H:i'),
            status: $sale->status->value,
            status_label: $sale->status->label(),
            is_open: $sale->isOpen(),
            total_amount: $sale->total_amount,
        );
    }
}
