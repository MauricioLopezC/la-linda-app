<?php

namespace App\Actions\Inventory;

use App\Models\Catalog\Article;
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
     * Register a manual stock movement transactionally.
     *
     * The user picks a movement type (which carries a fixed sign) and, per article, the positive
     * quantity that enters or leaves the warehouse. The signed delta stored on each line is
     * derived here once as `type.sign * quantity`; the user never types a total nor a difference.
     *
     * @param  array{
     *     warehouse_id: int,
     *     stock_movement_type_id: int,
     *     notes: string,
     *     user_id: int,
     *     items: array<int, array{article_id: int, quantity: float|int|string}>
     * }  $data
     *
     * @throws ValidationException
     */
    public function execute(array $data): StockMovement
    {
        $warehouse = Warehouse::query()->where('is_active', true)->findOrFail($data['warehouse_id']);

        /** @var StockMovementType $movementType */
        $movementType = StockMovementType::query()
            ->where('is_active', true)
            ->findOrFail($data['stock_movement_type_id']);

        if ($movementType->isAutomatic()) {
            throw ValidationException::withMessages([
                'stock_movement_type_id' => "El tipo de movimiento '{$movementType->name}' lo genera automáticamente otro módulo y no puede usarse en un movimiento manual.",
            ]);
        }

        return DB::transaction(function () use ($data, $warehouse, $movementType): StockMovement {
            $movement = StockMovement::create([
                'stock_movement_type_id' => $movementType->id,
                'warehouse_id' => $warehouse->id,
                'notes' => $data['notes'],
                'user_id' => $data['user_id'],
                'created_at' => now(),
            ]);

            foreach ($data['items'] as $itemData) {
                $articleId = (int) $itemData['article_id'];
                $quantity = round((float) $itemData['quantity'], 3);

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

                $systemQuantity = (float) $balance->quantity;
                $delta = round($movementType->sign * $quantity, 3);
                $newQuantity = round($systemQuantity + $delta, 3);

                if ($newQuantity < 0) {
                    throw ValidationException::withMessages([
                        'items' => "La existencia resultante de '{$movementType->name}' no puede quedar negativa.",
                    ]);
                }

                StockMovementItem::create([
                    'stock_movement_id' => $movement->id,
                    'article_id' => $articleId,
                    'quantity' => sprintf('%.3f', $delta),
                    'system_quantity' => sprintf('%.3f', $systemQuantity),
                ]);

                StockBalance::updateOrCreate(
                    [
                        'article_id' => $articleId,
                        'warehouse_id' => $warehouse->id,
                    ],
                    [
                        'quantity' => sprintf('%.3f', $newQuantity),
                    ]
                );
            }

            $movement->load([
                'warehouse.branch',
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
