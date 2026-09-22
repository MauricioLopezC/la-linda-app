<?php

namespace App\Data\Inventory;

use App\Models\Inventory\StockMovement;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class StockMovementDetailData extends Data
{
    /**
     * @param  Collection<int, StockMovementItemDetailData>  $items
     */
    public function __construct(
        public int $id,
        public string $type_name,
        public string $type_code,
        public int $warehouse_id,
        public string $warehouse_name,
        public string $branch_name,
        public ?string $notes,
        public int $user_id,
        public string $user_name,
        public string $created_at,
        public string $created_at_formatted,
        public Collection $items,
        public ?int $supplier_voucher_id,
        public ?string $supplier_voucher_formatted_number,
        public ?int $reversal_of_movement_id,
        public ?int $reversal_movement_id,
    ) {}

    public static function fromModel(StockMovement $movement): self
    {
        $tz = (string) config('app.timezone', 'America/Argentina/Buenos_Aires');
        $created = $movement->created_at?->copy()->setTimezone($tz) ?? now()->setTimezone($tz);

        $voucher = $movement->supplierVoucher ?? $movement->reversalOf?->supplierVoucher;
        $voucherFormattedNumber = $voucher !== null
            ? "{$voucher->letter->value} {$voucher->point_of_sale}-{$voucher->number}"
            : null;

        return new self(
            id: $movement->id,
            type_name: $movement->type->name,
            type_code: $movement->type->code,
            warehouse_id: $movement->warehouse_id,
            warehouse_name: $movement->warehouse->name,
            branch_name: $movement->warehouse->branch->name,
            notes: $movement->notes,
            user_id: $movement->user_id,
            user_name: $movement->user->name,
            created_at: $created->toISOString(true),
            created_at_formatted: $created->format('d/m/Y H:i:s'),
            items: StockMovementItemDetailData::collect($movement->items),
            supplier_voucher_id: $voucher?->id,
            supplier_voucher_formatted_number: $voucherFormattedNumber,
            reversal_of_movement_id: $movement->reversal_of_movement_id,
            reversal_movement_id: $movement->reversal?->id,
        );
    }
}
