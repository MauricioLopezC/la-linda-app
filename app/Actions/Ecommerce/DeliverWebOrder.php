<?php

namespace App\Actions\Ecommerce;

use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementItem;
use App\Models\Inventory\StockMovementType;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliverWebOrder
{
    /**
     * Transition a web order from 'en_preparacion' to 'entregado' and record the
     * stock deduction movement in the online warehouse.
     *
     * @throws ValidationException
     */
    public function execute(WebOrder $webOrder, User $user): WebOrder
    {
        return DB::transaction(function () use ($webOrder, $user): WebOrder {
            /** @var WebOrder $lockedOrder */
            $lockedOrder = WebOrder::query()->whereKey($webOrder->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status !== WebOrderStatus::InPreparation) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se puede entregar un pedido en preparación.',
                ]);
            }

            $lockedOrder->loadMissing(['sale.pointOfSale.warehouse', 'items.article']);

            $warehouse = null;
            $saleWarehouse = $lockedOrder->sale?->pointOfSale?->warehouse;
            if ($saleWarehouse !== null && $saleWarehouse->is_active && $saleWarehouse->is_online_channel) {
                $warehouse = $saleWarehouse;
            } else {
                /** @var Warehouse $warehouse */
                $warehouse = Warehouse::query()
                    ->where('is_online_channel', true)
                    ->where('is_active', true)
                    ->firstOrFail();
            }

            $alreadyDiscounted = $lockedOrder->sale !== null
                && StockMovement::query()->where('sale_id', $lockedOrder->sale->id)->exists();

            if (! $alreadyDiscounted && $lockedOrder->items->isNotEmpty()) {
                /** @var StockMovementType $movementType */
                $movementType = StockMovementType::query()
                    ->where('code', StockMovementType::CODE_WEB_ORDER_DELIVERY)
                    ->where('is_active', true)
                    ->firstOrFail();

                $movement = StockMovement::create([
                    'stock_movement_type_id' => $movementType->id,
                    'warehouse_id' => $warehouse->id,
                    'sale_id' => $lockedOrder->sale?->id,
                    'supplier_voucher_id' => null,
                    'reversal_of_movement_id' => null,
                    'notes' => "Salida por entrega de pedido web #{$lockedOrder->formattedNumber()}",
                    'user_id' => $user->id,
                    'created_at' => now(),
                ]);

                /** @var Collection<int, Collection<int, WebOrderItem>> $itemsByArticle */
                $itemsByArticle = $lockedOrder->items
                    ->whereNotNull('article_id')
                    ->groupBy('article_id');

                foreach ($itemsByArticle as $articleId => $articleItems) {
                    $totalQuantity = round((float) $articleItems->sum(fn (WebOrderItem $item): float => (float) $item->quantity), 3);

                    if ($totalQuantity <= 0) {
                        continue;
                    }

                    StockBalance::query()->insertOrIgnore([
                        'article_id' => (int) $articleId,
                        'warehouse_id' => $warehouse->id,
                        'quantity' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    /** @var StockBalance $balance */
                    $balance = StockBalance::query()
                        ->where('article_id', (int) $articleId)
                        ->where('warehouse_id', $warehouse->id)
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
                            'warehouse_id' => $warehouse->id,
                        ],
                        [
                            'quantity' => sprintf('%.3f', $newQuantity),
                        ]
                    );
                }
            }

            $lockedOrder->update([
                'status' => WebOrderStatus::Delivered,
                'delivered_at' => now(),
                'delivered_by' => $user->id,
            ]);

            $lockedOrder->refresh();

            return $lockedOrder;
        });
    }
}
