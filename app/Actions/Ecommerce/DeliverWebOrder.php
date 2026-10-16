<?php

namespace App\Actions\Ecommerce;

use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Catalog\Article;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementItem;
use App\Models\Inventory\StockMovementType;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliverWebOrder
{
    public function __construct(
        private readonly ResolveWebOrderWarehouse $resolveWarehouse,
    ) {}

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

            $articleIds = $lockedOrder->items()->orderBy('article_id')->pluck('article_id')->all();
            Article::query()->whereIn('id', $articleIds)->orderBy('id')->lockForUpdate()->get();

            $lockedOrder->loadMissing(['sale.pointOfSale.warehouse', 'sale.stockMovement.type', 'sale.stockMovement.warehouse', 'items.article']);

            $sale = $lockedOrder->sale;
            if ($sale === null || $sale->channel !== SaleChannel::Online || $sale->status !== SaleStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'sale' => 'Debe completarse la venta online confirmada del pedido antes de entregar.',
                ]);
            }

            $existingMovement = $sale->stockMovement;
            if ($existingMovement !== null && $existingMovement->type->code !== StockMovementType::CODE_WEB_ORDER_DELIVERY) {
                throw ValidationException::withMessages([
                    'sale' => 'La venta tiene asociado un movimiento que no corresponde a una entrega de pedido web.',
                ]);
            }

            $warehouse = $this->resolveWarehouse->execute($lockedOrder);
            if ($warehouse === null) {
                throw ValidationException::withMessages([
                    'warehouse' => 'No se encontró un depósito activo asignado al canal online para entregar el pedido.',
                ]);
            }

            if ($existingMovement === null && $lockedOrder->items->isNotEmpty()) {
                /** @var StockMovementType $movementType */
                $movementType = StockMovementType::query()
                    ->where('code', StockMovementType::CODE_WEB_ORDER_DELIVERY)
                    ->where('is_active', true)
                    ->firstOrFail();

                $movement = StockMovement::create([
                    'stock_movement_type_id' => $movementType->id,
                    'warehouse_id' => $warehouse->id,
                    'sale_id' => $sale->id,
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

                    $balance->update(['quantity' => sprintf('%.3f', $newQuantity)]);
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
