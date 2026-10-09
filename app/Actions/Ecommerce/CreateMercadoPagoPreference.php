<?php

namespace App\Actions\Ecommerce;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Generates a checkout payment preference on Mercado Pago API for an online order.
 */
class CreateMercadoPagoPreference
{
    use ConvertsMoneyToCents;

    /**
     * @return array{id: string, init_point: string, sandbox_init_point: string, redirect_url: string}
     */
    public function execute(WebOrder $order): array
    {
        if ($order->status !== WebOrderStatus::Pending) {
            throw new RuntimeException("Solo se pueden generar preferencias para pedidos pendientes de pago (pedido N.º {$order->formattedNumber()}).");
        }

        $accessToken = (string) config('services.mercadopago.access_token');
        if ($accessToken === '') {
            throw new RuntimeException('El token de acceso de Mercado Pago no está configurado (services.mercadopago.access_token).');
        }

        $order->loadMissing(['customer.account', 'items.article.unitOfMeasure']);

        // Verify that sum of items plus shipping cost matches total_amount exactly
        $itemsTotalCents = 0;
        foreach ($order->items as $item) {
            $itemsTotalCents += $this->moneyToCents($item->line_total);
        }
        $shippingCents = $this->moneyToCents($order->shipping_cost);
        $orderTotalCents = $this->moneyToCents($order->total_amount);

        if (($itemsTotalCents + $shippingCents) !== $orderTotalCents) {
            throw new RuntimeException("La suma de los ítems más el envío (\${$order->items_amount} + \${$order->shipping_cost}) no coincide con el total del pedido N.º {$order->formattedNumber()} (\${$order->total_amount}).");
        }

        $items = $order->items->map(function (WebOrderItem $item): array {
            $formattedQuantity = rtrim(rtrim((string) $item->quantity, '0'), '.');
            $unit = $item->article->unitOfMeasure?->abbreviation;
            $quantityLabel = filled($unit) ? "{$formattedQuantity} {$unit}" : $formattedQuantity;

            return [
                'id' => (string) $item->article_id,
                'title' => $item->article->description,
                'description' => "{$item->article->description} ({$quantityLabel})",
                'quantity' => 1,
                'unit_price' => (float) $item->line_total,
                'currency_id' => 'ARS',
            ];
        })->values()->all();

        if (bccomp((string) $order->shipping_cost, '0.00', 2) === 1) {
            $items[] = [
                'id' => 'shipping',
                'title' => 'Costo de envío a domicilio',
                'description' => 'Envío a domicilio',
                'quantity' => 1,
                'unit_price' => (float) $order->shipping_cost,
                'currency_id' => 'ARS',
            ];
        }

        $customer = $order->customer;
        $payerName = $customer->name;
        $payerEmail = $customer->account !== null ? $customer->account->email : ($customer->email ?? 'cliente@lalinda.test');

        $payload = [
            'items' => $items,
            'payer' => [
                'name' => $payerName,
                'email' => $payerEmail,
            ],
            'back_urls' => [
                'success' => route('tienda.orders.payment-return', ['web_order' => $order->id, 'status' => 'approved']),
                'pending' => route('tienda.orders.payment-return', ['web_order' => $order->id, 'status' => 'pending']),
                'failure' => route('tienda.orders.payment-return', ['web_order' => $order->id, 'status' => 'rejected']),
            ],
            'auto_return' => 'approved',
            'external_reference' => (string) $order->id,
            'notification_url' => route('webhooks.mercadopago'),
        ];

        $baseUrl = (string) config('services.mercadopago.base_url', 'https://api.mercadopago.com');
        $response = Http::baseUrl($baseUrl)
            ->withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->timeout(10)
            ->connectTimeout(3)
            ->retry([100, 500, 1000])
            ->post('/checkout/preferences', $payload);

        if (! $response->successful()) {
            throw new RuntimeException("Error al comunicarse con Mercado Pago (HTTP {$response->status()}): {$response->body()}");
        }

        /** @var array{id?: string, init_point?: string, sandbox_init_point?: string} $data */
        $data = $response->json();
        $preferenceId = (string) ($data['id'] ?? '');

        if ($preferenceId === '') {
            throw new RuntimeException('Mercado Pago no retornó un identificador de preferencia válido.');
        }

        $initPoint = (string) ($data['init_point'] ?? '');
        $sandboxInitPoint = (string) ($data['sandbox_init_point'] ?? $initPoint);
        $isSandbox = (bool) config('services.mercadopago.sandbox', true);

        // Atomically lock and verify order status before persisting preference
        $isStillPending = DB::transaction(function () use ($order, $preferenceId): bool {
            /** @var WebOrder $lockedOrder */
            $lockedOrder = WebOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status !== WebOrderStatus::Pending) {
                return false;
            }

            $lockedOrder->update(['mp_preference_id' => $preferenceId]);

            return true;
        });

        if (! $isStillPending) {
            throw new RuntimeException("El pedido N.º {$order->formattedNumber()} ya no se encuentra pendiente de pago.");
        }

        return [
            'id' => $preferenceId,
            'init_point' => $initPoint,
            'sandbox_init_point' => $sandboxInitPoint,
            'redirect_url' => $isSandbox ? $sandboxInitPoint : $initPoint,
        ];
    }
}
