<?php

namespace App\Data\Sales;

use App\Actions\Sales\GetCashSessionExpectedTotals;
use App\Models\Sales\CashCount;
use App\Models\Sales\CashMovement;
use App\Models\Sales\CashSession;
use App\Models\Sales\CashSessionClosureLine;
use Spatie\LaravelData\Data;

class CashSessionData extends Data
{
    /**
     * The totals and expected amounts are null and empty while the session is open: the
     * closing count is blind (HU-060), so they only show up in the closing summary.
     *

     * @param  array<int, CashMovementData>  $movements
     * @param  array<int, CashSessionExpectedTotalData>  $expected_totals
     * @param  array<int, CashSessionClosureLineData>  $closure_lines
     * @param  array<int, CashCountData>  $closing_counts
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
        public ?CashSessionTotalsData $totals,
        public array $movements,
        public array $expected_totals,
        public array $closure_lines,
        public array $closing_counts,
    ) {}

    public static function fromModel(CashSession $cashSession): self
    {
        $cashSession->loadMissing([
            'pointOfSale.warehouse.branch',
            'user',
            'movements' => fn ($query) => $query->with(['paymentMethod', 'user', 'sale'])->orderBy('id'),
            /* Written in the closing screen's order: cash first, then the other kinds. */
            'closureLines' => fn ($query) => $query->with('paymentMethod')->orderBy('id'),
            'closingCounts' => fn ($query) => $query->orderByDesc('denomination'),
        ]);

        $isOpen = $cashSession->isOpen();

        return new self(
            id: $cashSession->id,
            point_of_sale_id: $cashSession->point_of_sale_id,
            point_of_sale_number: $cashSession->pointOfSale->number,
            branch_name: $cashSession->pointOfSale->warehouse->branch->name,
            user_id: $cashSession->user_id,
            user_name: $cashSession->user->name,
            status: $cashSession->status->value,
            status_label: $cashSession->status->label(),
            is_open: $isOpen,
            opened_at: $cashSession->opened_at->toIso8601String(),
            opened_at_formatted: $cashSession->opened_at->format('d/m/Y H:i'),
            closed_at: $cashSession->closed_at?->toIso8601String(),
            closed_at_formatted: $cashSession->closed_at?->format('d/m/Y H:i'),
            closing_notes: $cashSession->closing_notes,
            totals: $isOpen ? null : CashSessionTotalsData::fromArray($cashSession->movementsSummary()),
            movements: $cashSession->movements->map(
                fn (CashMovement $movement): CashMovementData => CashMovementData::fromModel($movement),
            )->values()->all(),
            expected_totals: $isOpen ? [] : array_map(
                fn (array $total): CashSessionExpectedTotalData => CashSessionExpectedTotalData::fromArray($total),
                app(GetCashSessionExpectedTotals::class)->handle($cashSession),
            ),
            closure_lines: $cashSession->closureLines->map(
                fn (CashSessionClosureLine $line): CashSessionClosureLineData => CashSessionClosureLineData::fromModel($line),
            )->values()->all(),
            closing_counts: $cashSession->closingCounts->map(
                fn (CashCount $count): CashCountData => CashCountData::fromModel($count),
            )->values()->all(),
        );
    }
}
