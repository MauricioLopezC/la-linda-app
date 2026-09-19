<?php

namespace App\Data\Purchasing;

use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\SupplierVoucher;
use Spatie\LaravelData\Data;

class PurchaseOrderImputedVoucherData extends Data
{
    public function __construct(
        public int $id,
        public string $formatted_number,
        public string $type,
        public string $type_label,
        public string $issue_date_formatted,
        public string $status,
        public string $status_label,
        public string $total_amount,
        public string $quantity_received,
        public string $quantity_excess,
    ) {}

    public static function fromVoucherAndOrder(SupplierVoucher $voucher, PurchaseOrder $order): self
    {
        $orderItemIds = $order->items->pluck('id');

        $imputations = $voucher->items
            ->flatMap(fn ($item) => $item->imputations)
            ->filter(fn ($imp) => $orderItemIds->contains($imp->purchase_order_item_id));

        $receivedSum = $imputations->sum(fn ($imp) => (float) $imp->quantity_received);
        $excessSum = $imputations->sum(fn ($imp) => (float) $imp->quantity_excess);

        return new self(
            id: $voucher->id,
            formatted_number: $voucher->letter->value.' '.$voucher->point_of_sale.'-'.$voucher->number,
            type: $voucher->type->value,
            type_label: $voucher->type->label(),
            issue_date_formatted: $voucher->issue_date->format('d/m/Y'),
            status: $voucher->status->value,
            status_label: $voucher->status->label(),
            total_amount: (string) $voucher->total_amount,
            quantity_received: number_format($receivedSum, 3, '.', ''),
            quantity_excess: number_format($excessSum, 3, '.', ''),
        );
    }
}
