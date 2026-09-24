<?php

namespace App\Data\Purchasing;

use App\Models\Purchasing\PurchaseOrderItem;
use Spatie\LaravelData\Data;

class PurchaseOrderItemData extends Data
{
    public function __construct(
        public int $id,
        public int $article_id,
        public string $article_internal_code,
        public string $article_description,
        public string $unit_of_measure,
        public string $quantity,
        public string $quantity_received,
        public string $quantity_invoiced,
        public string $quantity_pending_to_receive,
        public string $quantity_pending_to_invoice,
        public string $quantity_excess_received,
        public string $quantity_excess_invoiced,
        public string $unit_price,
        public string $line_total,
    ) {}

    public static function fromModel(PurchaseOrderItem $item): self
    {
        return new self(
            id: $item->id,
            article_id: $item->article_id,
            article_internal_code: $item->article->internal_code,
            article_description: $item->article->description,
            unit_of_measure: $item->article->unitOfMeasure->abbreviation ?? $item->article->unitOfMeasure->name ?? 'u',
            quantity: (string) $item->quantity,
            quantity_received: $item->quantityReceived(),
            quantity_invoiced: $item->quantityInvoiced(),
            quantity_pending_to_receive: $item->quantityPendingToReceive(),
            quantity_pending_to_invoice: $item->quantityPendingToInvoice(),
            quantity_excess_received: $item->quantityExcessReceived(),
            quantity_excess_invoiced: $item->quantityExcessInvoiced(),
            unit_price: (string) $item->unit_price,
            line_total: (string) $item->line_total,
        );
    }
}
