<?php

namespace App\Data\Inventory;

use App\Models\Inventory\StockMovementItem;
use Spatie\LaravelData\Data;

class KardexEntryData extends Data
{
    public function __construct(
        public int $id,
        public int $stock_movement_id,
        public string $type_name,
        public ?string $notes,
        public string $user_name,
        public string $created_at_formatted,
        public string $quantity,
        public string $balance,
        public ?int $supplier_voucher_id,
        public ?string $supplier_voucher_formatted_number,
        public ?int $sale_id,
        public ?string $system_quantity,
        public bool $is_conflict,
    ) {}

    /**
     * Build an entry from a ledger row of ConsultArticleKardex, which carries the running `balance`.
     */
    public static function fromModel(StockMovementItem $item): self
    {
        $movement = $item->stockMovement;
        $tz = (string) config('app.timezone', 'America/Argentina/Buenos_Aires');
        $created = $movement->created_at?->copy()->setTimezone($tz) ?? now()->setTimezone($tz);

        $voucher = $movement->supplierVoucher ?? $movement->reversalOf?->supplierVoucher;
        $voucherFormattedNumber = $voucher !== null
            ? "{$voucher->letter->value} {$voucher->point_of_sale}-{$voucher->number}"
            : null;

        $isConflict = $item->system_quantity !== null
            && round((float) $item->system_quantity + (float) $item->quantity, 3) < 0;

        return new self(
            id: $item->id,
            stock_movement_id: $movement->id,
            type_name: $movement->type->name,
            notes: $movement->notes,
            user_name: $movement->user->name,
            created_at_formatted: $created->format('d/m/Y H:i:s'),
            quantity: sprintf('%.3f', (float) $item->quantity),
            balance: sprintf('%.3f', (float) $item->getAttribute('balance')),
            supplier_voucher_id: $voucher?->id,
            supplier_voucher_formatted_number: $voucherFormattedNumber,
            sale_id: $movement->sale_id,
            system_quantity: $item->system_quantity !== null ? sprintf('%.3f', (float) $item->system_quantity) : null,
            is_conflict: $isConflict,
        );
    }
}
