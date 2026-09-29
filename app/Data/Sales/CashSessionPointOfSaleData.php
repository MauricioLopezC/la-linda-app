<?php

namespace App\Data\Sales;

use App\Models\Sales\PointOfSale;
use Spatie\LaravelData\Data;

/**
 * An active point of sale offered when opening a session, with who holds it if it is taken.
 */
class CashSessionPointOfSaleData extends Data
{
    public function __construct(
        public int $id,
        public int $number,
        public string $branch_name,
        public ?string $open_session_user_name,
    ) {}

    public static function fromModel(PointOfSale $pointOfSale): self
    {
        return new self(
            id: $pointOfSale->id,
            number: $pointOfSale->number,
            branch_name: $pointOfSale->warehouse->branch->name,
            open_session_user_name: $pointOfSale->openCashSession?->user->name,
        );
    }
}
