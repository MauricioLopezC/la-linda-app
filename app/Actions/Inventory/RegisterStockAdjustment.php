<?php

namespace App\Actions\Inventory;

use App\Models\Catalog\Article;
use App\Models\Inventory\StockAdjustmentReason;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementItem;
use App\Models\Inventory\StockMovementType;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterStockAdjustment
{
    /**
     * Register an inventory stock adjustment transactionally.
     *
     * @param  array{
     *     warehouse_id: int,
     *     stock_adjustment_reason_id?: ?int,
     *     notes?: ?string,
     *     user_id: int,
     *     items: array<int, array{article_id: int, counted_quantity: float|int|string}>
     * }  $data
     *
     * @throws ValidationException
     */
    public function execute(array $data): StockMovement
    {
        $warehouse = Warehouse::query()->where('is_active', true)->findOrFail($data['warehouse_id']);

        $movementType = StockMovementType::query()
            ->where('code', StockMovementType::CODE_INVENTORY_ADJUSTMENT)
            ->firstOrFail();

        // 5. Motivo obligatorio solo cuando el tipo es inventory_adjustment
        $reasonId = $data['stock_adjustment_reason_id'] ?? null;
        if ($movementType->code === StockMovementType::CODE_INVENTORY_ADJUSTMENT && ! $reasonId) {
            throw ValidationException::withMessages([
                'stock_adjustment_reason_id' => 'El motivo de ajuste es obligatorio para movimientos de tipo ajuste de inventario.',
            ]);
        }

        $reason = null;
        if ($reasonId) {
            $reason = StockAdjustmentReason::query()->where('is_active', true)->findOrFail($reasonId);
        }

        return DB::transaction(function () use ($data, $warehouse, $reason, $movementType): StockMovement {
            $movement = StockMovement::create([
                'stock_movement_type_id' => $movementType->id,
                'warehouse_id' => $warehouse->id,
                'stock_adjustment_reason_id' => $reason?->id,
                'notes' => $data['notes'] ?? null,
                'user_id' => $data['user_id'],
                'created_at' => now(),
            ]);

            $registeredItemsCount = 0;

            foreach ($data['items'] as $itemData) {
                $articleId = (int) $itemData['article_id'];
                $countedQuantity = (float) $itemData['counted_quantity'];

                // Ensure article exists
                Article::query()->findOrFail($articleId);

                // Garantiza que exista la fila antes de bloquearla: si dos transacciones ajustan
                // el mismo artículo/depósito por primera vez a la vez, la segunda espera el lock
                // en vez de competir por insertar la primera fila.
                StockBalance::query()->insertOrIgnore([
                    'article_id' => $articleId,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                /** @var StockBalance $balance */
                $balance = StockBalance::query()
                    ->where('article_id', $articleId)
                    ->where('warehouse_id', $warehouse->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // 1 & 3. Lee stock_balances.quantity y guarda en system_quantity
                $systemQuantity = (float) $balance->quantity;
                $delta = round($countedQuantity - $systemQuantity, 3);

                // 4. Aserción de signo: validar con stock_movement_types.sign si está definido
                if ($movementType->sign !== null) {
                    if ($movementType->sign === 1 && $delta < 0) {
                        throw ValidationException::withMessages([
                            'items' => "Un movimiento de tipo '{$movementType->name}' no puede restar existencias.",
                        ]);
                    }
                    if ($movementType->sign === -1 && $delta > 0) {
                        throw ValidationException::withMessages([
                            'items' => "Un movimiento de tipo '{$movementType->name}' no puede sumar existencias.",
                        ]);
                    }
                }

                // Inserta en stock_movement_items sólo si hay delta (CHECK quantity <> 0)
                if (abs($delta) > 0.0001) {
                    StockMovementItem::create([
                        'stock_movement_id' => $movement->id,
                        'article_id' => $articleId,
                        'quantity' => sprintf('%.3f', $delta),
                        'system_quantity' => sprintf('%.3f', $systemQuantity),
                    ]);

                    $registeredItemsCount++;
                }

                // Upsert de stock_balances con la cantidad física contada
                StockBalance::updateOrCreate(
                    [
                        'article_id' => $articleId,
                        'warehouse_id' => $warehouse->id,
                    ],
                    [
                        'quantity' => sprintf('%.3f', $countedQuantity),
                    ]
                );
            }

            if ($registeredItemsCount === 0) {
                throw ValidationException::withMessages([
                    'items' => 'El recuento físico coincide exactamente con el stock del sistema; no hay diferencias de stock que justifiquen registrar un movimiento.',
                ]);
            }

            $movement->load([
                'warehouse.branch',
                'reason',
                'user',
                'type',
                'items.article.unitOfMeasure',
                'items.article.category',
                'items.article.brand',
            ]);

            return $movement;
        });
    }
}
