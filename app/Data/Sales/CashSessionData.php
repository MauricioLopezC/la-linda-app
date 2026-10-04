<?php

namespace App\Data\Sales;

use App\Models\Sales\CashMovement;
use App\Models\Sales\CashSession;
use Spatie\LaravelData\Data;

class CashSessionData extends Data
{
    /**
     * @param  array<int, CashMovementData>  $movements
     */
    public function __construct(
        public int $id,
        public int $point_of_sale_id,
        public int $point_of_sale_number,
        public string $branch_name,
        public int $user_id,
        public string $user_name,
        public string $status,
        public string $status_label,
        public bool $is_open,
        public string $opened_at,
        public string $opened_at_formatted,
        public ?string $closed_at,
        public ?string $closed_at_formatted,
        public ?string $closing_notes,
        public CashSessionTotalsData $totals,
        public array $movements,
    ) {}

    public static function fromModel(CashSession $cashSession): self
    {
        $cashSession->loadMissing([
            'pointOfSale.warehouse.branch',
            'user',
            'movements' => fn ($query) => $query->with(['paymentMethod', 'user', 'sale'])->orderBy('id'),
        ]);

        return new self(
            id: $cashSession->id,
            point_of_sale_id: $cashSession->point_of_sale_id,
            point_of_sale_number: $cashSession->pointOfSale->number,
            branch_name: $cashSession->pointOfSale->warehouse->branch->name,
            user_id: $cashSession->user_id,
            user_name: $cashSession->user->name,
            status: $cashSession->status->value,
            status_label: $cashSession->status->label(),
            is_open: $cashSession->isOpen(),
            opened_at: $cashSession->opened_at->toIso8601String(),
            opened_at_formatted: $cashSession->opened_at->format('d/m/Y H:i'),
            closed_at: $cashSession->closed_at?->toIso8601String(),
            closed_at_formatted: $cashSession->closed_at?->format('d/m/Y H:i'),
            closing_notes: $cashSession->closing_notes,
            totals: CashSessionTotalsData::fromArray($cashSession->movementsSummary()),
            movements: $cashSession->movements->map(
                fn (CashMovement $movement): CashMovementData => CashMovementData::fromModel($movement),
            )->values()->all(),
        );
    }
}
