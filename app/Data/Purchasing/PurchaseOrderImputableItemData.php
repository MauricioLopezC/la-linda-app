<?php

namespace App\Data\Purchasing;

use App\Models\Purchasing\PurchaseOrderItem;
use Spatie\LaravelData\Data;

class PurchaseOrderImputableItemData extends Data
{
    public function __construct(
        public int $id,
        public int $purchase_order_id,
        public string $purchase_order_number,
        public int $article_id,
        public string $article_internal_code,
        public string $article_description,
        public string $unit_of_measure,
        public string $unit_price,
        public string $quantity_requested,
        public string $quantity_received,
        public string $quantity_pending,
    ) {}

    public static function fromModel(PurchaseOrderItem $item): self
    {
        return new self(
            id: $item->id,
            purchase_order_id: $item->purchase_order_id,
            purchase_order_number: $item->purchaseOrder->order_number,
            article_id: $item->article_id,
            article_internal_code: $item->article->internal_code,
            article_description: $item->article->description,
            unit_of_measure: $item->article->unitOfMeasure->abbreviation ?? $item->article->unitOfMeasure->name ?? 'u',
            unit_price: (string) $item->unit_price,
            quantity_requested: (string) $item->quantity,
            quantity_received: $item->quantityReceived(),
            quantity_pending: $item->quantityPending(),
        );
    }
}
