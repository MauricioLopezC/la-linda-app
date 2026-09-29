<?php

namespace App\Data\Sales;

use App\Models\Sales\CashSession;
use Spatie\LaravelData\Data;

/**
 * The logged-in cashier's open session, shared with every page to show where they are working.
 */
class OpenCashSessionData extends Data
{
    public function __construct(
        public int $id,
        public int $point_of_sale_id,
        public int $point_of_sale_number,
        public string $branch_name,
        public string $opened_at,
        public string $opening_amount,
    ) {}

    public static function fromModel(CashSession $cashSession): self
    {
        return new self(
            id: $cashSession->id,
            point_of_sale_id: $cashSession->point_of_sale_id,
            point_of_sale_number: $cashSession->pointOfSale->number,
            branch_name: $cashSession->pointOfSale->warehouse->branch->name,
            opened_at: $cashSession->opened_at->toIso8601String(),
            opening_amount: $cashSession->opening_amount,
        );
    }
}
