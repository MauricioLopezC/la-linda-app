<?php

namespace App\Actions\Ecommerce;

use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Ecommerce\WebOrder;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Fetches and processes an incoming payment notification from Mercado Pago API.
 */
class ProcessMercadoPagoPaymentNotification
{
    public function __construct(
        private readonly MarkWebOrderAsPaid $markWebOrderAsPaid,
    ) {}

    /**
     * @return array{status_code: int, data: array<string, mixed>}
     *
     * @throws \InvalidArgumentException
     * @throws RuntimeException
     */
    public function execute(string $paymentId): array
    {
        $accessToken = (string) config('services.mercadopago.access_token');
        if ($accessToken === '') {
            Log::error('Mercado Pago webhook: access token is not configured.');

            throw new RuntimeException('El token de acceso de Mercado Pago no está configurado (services.mercadopago.access_token).');
        }

        $baseUrl = (string) config('services.mercadopago.base_url', 'https://api.mercadopago.com');

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($accessToken)
                ->acceptJson()
                ->timeout(10)
                ->connectTimeout(3)
                ->retry(
                    times: [100, 500, 1000],
                    sleepMilliseconds: 0,
                    when: fn (\Throwable $exception): bool => $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && $exception->response->serverError()),
                    throw: false,
                )
                ->get('/v1/payments/'.rawurlencode($paymentId));
        } catch (ConnectionException $exception) {
            Log::error("Mercado Pago webhook: connection failed querying payment ID {$paymentId}: {$exception->getMessage()}");

            throw new RuntimeException('Error de conexión al consultar Mercado Pago.', 0, $exception);
        }

        if ($response->status() === 404) {
            Log::warning("Mercado Pago webhook: payment ID {$paymentId} not found in API.");

            return [
                'status_code' => 200,
                'data' => [
                    'status' => 'ignored',
                    'reason' => 'payment_not_found',
                ],
            ];
        }

        if (! $response->successful()) {
            Log::error("Mercado Pago webhook: API returned status {$response->status()}: {$response->body()}");

            throw new RuntimeException("Mercado Pago API retornó estado HTTP {$response->status()}.");
        }

        /** @var array{id?: int|string, status?: string, external_reference?: string|int|null, transaction_amount?: float|int|numeric-string, date_approved?: string|null} $payment */
        $payment = $response->json();

        $externalReference = (string) ($payment['external_reference'] ?? '');
        $status = (string) ($payment['status'] ?? '');
        $transactionAmount = (float) ($payment['transaction_amount'] ?? 0);
        $dateApproved = $payment['date_approved'] ?? null;

        if ($externalReference === '') {
            Log::warning("Mercado Pago webhook: payment {$paymentId} missing external_reference.");

            return [
                'status_code' => 200,
                'data' => [
                    'status' => 'ignored',
                    'reason' => 'missing_external_reference',
                ],
            ];
        }

        /** @var WebOrder|null $order */
        $order = WebOrder::query()->find($externalReference);

        if ($order === null) {
            Log::warning("Mercado Pago webhook: order with ID {$externalReference} not found.");

            return [
                'status_code' => 200,
                'data' => [
                    'status' => 'ignored',
                    'reason' => 'order_not_found',
                ],
            ];
        }

        // Only approved payments mark orders as paid
        if ($status !== 'approved') {
            return [
                'status_code' => 200,
                'data' => [
                    'status' => 'acknowledged',
                    'payment_status' => $status,
                    'order_status' => $order->status->value,
                ],
            ];
        }

        $formattedAmount = number_format($transactionAmount, 2, '.', '');
        $paidAt = filled($dateApproved) ? Carbon::parse((string) $dateApproved) : now();

        $this->markWebOrderAsPaid->execute(
            order: $order,
            paymentId: $paymentId,
            paidAmount: $formattedAmount,
            paidAt: $paidAt,
        );

        return [
            'status_code' => 200,
            'data' => [
                'status' => 'ok',
                'order_status' => WebOrderStatus::Paid->value,
            ],
        ];
    }
}
