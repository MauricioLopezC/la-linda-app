<?php

namespace App\Actions\Inventory;

use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementItem;
use App\Models\Inventory\StockMovementType;
use App\Models\Purchasing\SupplierVoucher;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CreateStockMovementFromVoucher
{
    /**
     * Creates an automatic, immutable stock movement for a confirmed supplier remito.
     *
     * @throws ValidationException
     */
    public function handle(SupplierVoucher $voucher, int $userId): StockMovement
    {
        if (! $voucher->type->isRemito()) {
            throw new \InvalidArgumentException('Solo los comprobantes de tipo remito generan movimiento de stock.');
        }

        if ($voucher->status !== SupplierVoucherStatus::Confirmed) {
            throw ValidationException::withMessages([
                'status' => 'El remito debe encontrarse en estado confirmado para generar movimiento de stock.',
            ]);
        }

        if ($voucher->warehouse_id === null) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'El remito no tiene un depósito asignado.',
            ]);
        }

        // Bloquear comprobante y verificar idempotencia
        /** @var SupplierVoucher $lockedVoucher */
        $lockedVoucher = SupplierVoucher::query()->lockForUpdate()->findOrFail($voucher->id);

        $existingMovement = StockMovement::query()
            ->where('supplier_voucher_id', $lockedVoucher->id)
            ->first();

        if ($existingMovement !== null) {
            return $existingMovement;
        }

        $lockedVoucher->loadMissing('items.article');

        /** @var Collection<int, Collection<int, mixed>> $itemsByArticle */
        $itemsByArticle = $lockedVoucher->items
            ->whereNotNull('article_id')
            ->groupBy('article_id');

        if ($itemsByArticle->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'El remito debe contener al menos un renglón con artículo de catálogo para generar stock.',
            ]);
        }

        /** @var StockMovementType $movementType */
        $movementType = StockMovementType::query()
            ->where('code', StockMovementType::CODE_PURCHASE_ENTRY)
            ->where('is_active', true)
            ->firstOrFail();

        $formattedNumber = "{$lockedVoucher->letter->value} {$lockedVoucher->point_of_sale}-{$lockedVoucher->number}";
        $notes = "Ingreso automático por remito {$formattedNumber}";

        $movement = StockMovement::create([
            'stock_movement_type_id' => $movementType->id,
            'warehouse_id' => $lockedVoucher->warehouse_id,
            'supplier_voucher_id' => $lockedVoucher->id,
            'reversal_of_movement_id' => null,
            'notes' => $notes,
            'user_id' => $userId,
            'created_at' => now(),
        ]);

        foreach ($itemsByArticle as $articleId => $articleItems) {
            $totalQuantity = round((float) $articleItems->sum(fn ($item) => (float) $item->quantity), 3);

            if ($totalQuantity <= 0) {
                continue;
            }

            StockBalance::query()->insertOrIgnore([
                'article_id' => (int) $articleId,
                'warehouse_id' => $lockedVoucher->warehouse_id,
                'quantity' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /** @var StockBalance $balance */
            $balance = StockBalance::query()
                ->where('article_id', (int) $articleId)
                ->where('warehouse_id', $lockedVoucher->warehouse_id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentQuantity = (float) $balance->quantity;
            $delta = round($movementType->sign * $totalQuantity, 3);
            $newQuantity = round($currentQuantity + $delta, 3);

            StockMovementItem::create([
                'stock_movement_id' => $movement->id,
                'article_id' => (int) $articleId,
                'quantity' => sprintf('%.3f', $delta),
                'system_quantity' => null,
            ]);

            StockBalance::updateOrCreate(
                [
                    'article_id' => (int) $articleId,
                    'warehouse_id' => $lockedVoucher->warehouse_id,
                ],
                [
                    'quantity' => sprintf('%.3f', $newQuantity),
                ]
            );
        }

        return $movement;
    }
}
