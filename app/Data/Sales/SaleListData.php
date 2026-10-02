<?php

namespace App\Data\Sales;

use App\Models\Sales\Sale;
use Spatie\LaravelData\Data;

class SaleListData extends Data
{
    public function __construct(
        public int $id,
        public ?int $cash_session_id,
        public string $opened_at_formatted,
        public int $point_of_sale_number,
        public string $branch_name,
        public string $customer_name,
        public ?string $user_name,
        public string $status,
        public string $status_label,
        public string $invoice_type,
        public string $invoice_type_label,
        public int $items_count,
        public string $total_amount,
    ) {}

    /**
     * Expects pointOfSale.warehouse.branch, customer and user loaded, and items_count.
     */
    public static function fromModel(Sale $sale): self
    {
        $invoiceType = $sale->invoiceType();

        return new self(
            id: $sale->id,
            cash_session_id: $sale->cash_session_id,
            opened_at_formatted: $sale->opened_at->format('d/m/Y H:i'),
            point_of_sale_number: $sale->pointOfSale->number,
            branch_name: $sale->pointOfSale->warehouse->branch->name,
            customer_name: $sale->customer->name,
            user_name: $sale->user?->name,
            status: $sale->status->value,
            status_label: $sale->status->label(),
            invoice_type: $invoiceType->value,
            invoice_type_label: $invoiceType->label(),
            items_count: (int) $sale->getAttribute('items_count'),
            total_amount: $sale->total_amount,
        );
    }
}
