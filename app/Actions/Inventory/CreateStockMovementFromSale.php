<?php

namespace App\Actions\Inventory;

use App\Enums\Sales\SaleStatus;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementItem;
use App\Models\Inventory\StockMovementType;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CreateStockMovementFromSale
{
    /**
     * Create an automatic, immutable stock movement for a confirmed sale.
     *
     * A sale is never blocked by missing stock (EPIC-06): if the article is at the till,
     * it is sold, and the balance may go negative. The previous balance is captured in
     * system_quantity to mark conflict lines.
     *
     * @throws ValidationException
     */
    public function handle(Sale $sale, int $userId): StockMovement
    {
        $existingMovement = StockMovement::query()
            ->where('sale_id', $sale->id)
            ->first();

        if ($existingMovement !== null) {
            return $existingMovement;
        }

        /** @var Sale $lockedSale */
        $lockedSale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

        if ($lockedSale->status !== SaleStatus::Confirmed) {
            throw ValidationException::withMessages([
                'sale' => 'Solo se genera movimiento de stock para ventas confirmadas.',
            ]);
        }

        $lockedSale->loadMissing(['pointOfSale', 'items.article']);

        if ($lockedSale->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'La venta no contiene artículos para mover stock.',
            ]);
        }

        $existingMovement = StockMovement::query()
            ->where('sale_id', $lockedSale->id)
            ->first();

        if ($existingMovement !== null) {
            return $existingMovement;
        }

        /** @var StockMovementType $movementType */
        $movementType = StockMovementType::query()
            ->where('code', StockMovementType::CODE_SALE_EXIT)
            ->where('is_active', true)
            ->first() ?? StockMovementType::firstOrCreate(
                ['code' => StockMovementType::CODE_SALE_EXIT],
                [
                    'name' => 'Salida por Venta',
                    'name_normalized' => 'salida por venta',
                    'sign' => -1,
                    'description' => 'Egreso de mercadería por confirmación de venta de mostrador o e-commerce',
                    'is_system' => true,
                    'is_active' => true,
                ]
            );

        $warehouseId = $lockedSale->pointOfSale->warehouse_id;

        $movement = StockMovement::create([
            'stock_movement_type_id' => $movementType->id,
            'warehouse_id' => $warehouseId,
            'sale_id' => $lockedSale->id,
            'supplier_voucher_id' => null,
            'reversal_of_movement_id' => null,
            'notes' => "Salida automática por venta #{$lockedSale->id}",
            'user_id' => $userId,
            'created_at' => now(),
        ]);

        /** @var Collection<int, Collection<int, SaleItem>> $itemsByArticle */
        $itemsByArticle = $lockedSale->items
            ->whereNotNull('article_id')
            ->groupBy('article_id');

        foreach ($itemsByArticle as $articleId => $articleItems) {
            $totalQuantity = round((float) $articleItems->sum(fn (SaleItem $item) => (float) $item->quantity), 3);

            if ($totalQuantity <= 0) {
                continue;
            }

            StockBalance::query()->insertOrIgnore([
                'article_id' => (int) $articleId,
                'warehouse_id' => $warehouseId,
                'quantity' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /** @var StockBalance $balance */
            $balance = StockBalance::query()
                ->where('article_id', (int) $articleId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->firstOrFail();

            $systemQuantity = (float) $balance->quantity;
            $delta = round($movementType->sign * $totalQuantity, 3);
            $newQuantity = round($systemQuantity + $delta, 3);

            StockMovementItem::create([
                'stock_movement_id' => $movement->id,
                'article_id' => (int) $articleId,
                'quantity' => sprintf('%.3f', $delta),
                'system_quantity' => sprintf('%.3f', $systemQuantity),
            ]);

            StockBalance::updateOrCreate(
                [
                    'article_id' => (int) $articleId,
                    'warehouse_id' => $warehouseId,
                ],
                [
                    'quantity' => sprintf('%.3f', $newQuantity),
                ]
            );
        }

        return $movement;
    }
}
