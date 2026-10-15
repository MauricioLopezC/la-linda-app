<?php

namespace App\Actions\Ecommerce;

use App\Actions\Sales\IssueInvoice;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Inventory\Warehouse;
use App\Models\Pricing\PriceList;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class ConvertPaidWebOrderToSale
{
    public function __construct(
        protected IssueInvoice $issueInvoice,
    ) {}

    /**
     * Converts a paid WebOrder into a confirmed online Sale and issues its fiscal invoice (EPIC-15).
     * Strictly idempotent: if a sale already exists for this web order, it returns it and ensures
     * its invoice is issued.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws ValidationException
     */
    public function execute(WebOrder $order): Sale
    {
        if ($order->status !== WebOrderStatus::Paid) {
            throw new InvalidArgumentException(
                "Solo se puede procesar como venta un pedido web en estado pagado (estado actual: {$order->status->label()})."
            );
        }

        return DB::transaction(function () use ($order): Sale {
            /** @var Sale|null $existingSale */
            $existingSale = Sale::query()
                ->where('web_order_id', $order->id)
                ->lockForUpdate()
                ->first();

            if ($existingSale !== null) {
                if ($existingSale->items()->exists() && ! $existingSale->invoice()->exists()) {
                    $this->issueInvoice->handle($existingSale, null);
                }

                return $existingSale->load(['invoice.pointOfSale', 'items.article', 'customer']);
            }

            /** @var Warehouse|null $warehouse */
            $warehouse = Warehouse::query()
                ->online()
                ->active()
                ->first();

            if ($warehouse === null) {
                throw new RuntimeException('No se encontró un depósito activo asignado al canal online.');
            }

            /** @var PointOfSale|null $pointOfSale */
            $pointOfSale = PointOfSale::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('is_active', true)
                ->first();

            if ($pointOfSale === null) {
                throw new RuntimeException('No se encontró un punto de venta activo para el depósito del canal online.');
            }

            $order->loadMissing(['customer', 'items.article.vatRate']);

            $vatRatesByItemId = [];
            foreach ($order->items as $orderItem) {
                $vatRate = $orderItem->article->vatRate;

                if ($vatRate === null) {
                    throw ValidationException::withMessages([
                        'article_id' => "El artículo \"{$orderItem->article->description}\" no tiene alícuota de IVA asignada.",
                    ]);
                }

                $vatRatesByItemId[$orderItem->id] = $vatRate;
            }

            $priceListId = $order->items->first()->price_list_id
                ?? PriceList::query()->forChannel(PriceListChannel::Online)->active()->currentlyValid()->value('id')
                ?? $order->customer->price_list_id
                ?? PriceList::query()->value('id');

            /** @var Sale $sale */
            $sale = Sale::create([
                'point_of_sale_id' => $pointOfSale->id,
                'channel' => SaleChannel::Online,
                'cash_session_id' => null,
                'web_order_id' => $order->id,
                'price_list_id' => $priceListId,
                'customer_id' => $order->customer_id,
                'user_id' => null,
                'opened_at' => $order->placed_at,
                'status' => SaleStatus::Confirmed,
                'confirmed_at' => $order->paid_at ?? now(),
                'shipping_amount' => $order->shipping_cost,
                'total_amount' => $order->total_amount,
            ]);

            /** @var WebOrderItem $orderItem */
            foreach ($order->items as $orderItem) {
                $vatRate = $vatRatesByItemId[$orderItem->id];

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'article_id' => $orderItem->article_id,
                    'quantity' => $orderItem->quantity,
                    'unit_price' => $orderItem->unit_price,
                    'price_list_id' => $orderItem->price_list_id ?? $priceListId,
                    'line_total' => $orderItem->line_total,
                    'vat_rate_id' => $vatRate->id,
                    'vat_rate' => number_format($vatRate->percentage, 2, '.', ''),
                ]);
            }

            // Issue invoice (no user for online sales) if sale has items
            if ($sale->items()->exists()) {
                $this->issueInvoice->handle($sale, null);
            }

            return $sale->load(['invoice.pointOfSale', 'items.article', 'customer']);
        });
    }
}
