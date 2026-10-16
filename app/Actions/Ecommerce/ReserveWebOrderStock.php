<?php

namespace App\Actions\Ecommerce;

use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Catalog\Article;
use App\Models\Ecommerce\WebOrder;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReserveWebOrderStock
{
    public function __construct(private readonly GetOnlineAvailability $getOnlineAvailability) {}

    public function execute(WebOrder $order): WebOrder
    {
        return DB::transaction(function () use ($order): WebOrder {
            $lockedOrder = WebOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status !== WebOrderStatus::Pending) {
                throw ValidationException::withMessages(['payment' => 'El pedido ya no está pendiente de pago.']);
            }

            if ($lockedOrder->stock_reserved_until !== null) {
                if (! $lockedOrder->stock_reserved_until->isFuture()) {
                    throw ValidationException::withMessages(['payment' => 'Estamos verificando el pago anterior antes de renovar la reserva. Intentá nuevamente en unos instantes.']);
                }

                if ($lockedOrder->mp_init_point === null) {
                    throw ValidationException::withMessages(['payment' => 'El pago ya se está preparando. Intentá nuevamente en unos instantes.']);
                }

                return $lockedOrder;
            }

            $lockedOrder->load('items.article');
            if ($lockedOrder->items->isEmpty()) {
                throw ValidationException::withMessages(['payment' => 'El pedido no contiene artículos para reservar.']);
            }

            $articleIds = $lockedOrder->items->pluck('article_id')->sort()->values()->all();
            Article::query()->whereIn('id', $articleIds)->orderBy('id')->lockForUpdate()->get();
            $warehouse = Warehouse::query()->online()->active()->first();
            StockBalance::query()->where('warehouse_id', $warehouse?->id)
                ->whereIn('article_id', $articleIds)->orderBy('article_id')->lockForUpdate()->get();
            $availabilities = $this->getOnlineAvailability->forArticles($articleIds);

            foreach ($lockedOrder->items as $item) {
                $available = $availabilities[$item->article_id]->availableQuantity ?? '0.000';

                if (bccomp($item->quantity, $available, 3) > 0) {
                    throw ValidationException::withMessages([
                        'payment' => "No hay stock suficiente para pagar el pedido: {$item->article->description} (solicitado: {$item->quantity}, disponible: {$available}).",
                    ]);
                }
            }

            $lockedOrder->update([
                'stock_reserved_until' => now()->addMinutes(max(1, (int) config('ecommerce.reservation_minutes', 15))),
                'payment_reservation_token' => (string) Str::uuid(),
                'mp_preference_id' => null,
                'mp_init_point' => null,
                'mp_sandbox_init_point' => null,
            ]);

            return $lockedOrder;
        });
    }
}
