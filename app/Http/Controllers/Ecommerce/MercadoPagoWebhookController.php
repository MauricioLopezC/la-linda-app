<?php

namespace App\Http\Controllers\Ecommerce;

use App\Actions\Ecommerce\MarkWebOrderAsPaid;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Ecommerce\WebOrder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoWebhookController extends Controller
{
    /**
     * Handle incoming notifications from Mercado Pago webhook.
     */
    public function __invoke(Request $request, MarkWebOrderAsPaid $markWebOrderAsPaid): JsonResponse
    {
        $type = $request->input('type') ?? $request->input('topic') ?? $request->query('topic');
        $paymentId = $request->input('data.id') ?? $request->input('id') ?? $request->query('id');

        // Gracefully ignore non-payment notifications or notifications without an id
        if ($type !== 'payment' && $type !== null && ! str_contains((string) $type, 'payment')) {
            return response()->json(['status' => 'ignored', 'reason' => 'non_payment_event']);
        }

        if (blank($paymentId)) {
            return response()->json(['status' => 'ignored', 'reason' => 'missing_payment_id']);
        }

        $paymentId = (string) $paymentId;
        $accessToken = (string) config('services.mercadopago.access_token');
        if ($accessToken === '') {
            Log::error('Mercado Pago webhook: access token is not configured.');

            return response()->json(['error' => 'mercadopago_not_configured'], 500);
        }

        $baseUrl = (string) config('services.mercadopago.base_url', 'https://api.mercadopago.com');

        $response = Http::baseUrl($baseUrl)
            ->withToken($accessToken)
            ->acceptJson()
            ->timeout(10)
            ->connectTimeout(3)
            ->retry([100, 500, 1000])
            ->get('/v1/payments/'.rawurlencode($paymentId));

        if ($response->status() === 404) {
            Log::warning("Mercado Pago webhook: payment ID {$paymentId} not found in API.");

            return response()->json(['status' => 'ignored', 'reason' => 'payment_not_found']);
        }

        if (! $response->successful()) {
            Log::error("Mercado Pago webhook: API returned status {$response->status()}: {$response->body()}");

            return response()->json(['error' => 'mercadopago_api_error'], 500);
        }

        /** @var array{id?: int|string, status?: string, external_reference?: string|int|null, transaction_amount?: float|int|numeric-string, date_approved?: string|null} $payment */
        $payment = $response->json();

        $externalReference = (string) ($payment['external_reference'] ?? '');
        $status = (string) ($payment['status'] ?? '');
        $transactionAmount = (float) ($payment['transaction_amount'] ?? 0);
        $dateApproved = $payment['date_approved'] ?? null;

        if ($externalReference === '') {
            Log::warning("Mercado Pago webhook: payment {$paymentId} missing external_reference.");

            return response()->json(['status' => 'ignored', 'reason' => 'missing_external_reference']);
        }

        /** @var WebOrder|null $order */
        $order = WebOrder::query()->find($externalReference);

        if ($order === null) {
            Log::warning("Mercado Pago webhook: order with ID {$externalReference} not found.");

            return response()->json(['status' => 'ignored', 'reason' => 'order_not_found']);
        }

        // Only approved payments mark orders as paid
        if ($status !== 'approved') {
            return response()->json([
                'status' => 'acknowledged',
                'payment_status' => $status,
                'order_status' => $order->status->value,
            ]);
        }

        $formattedAmount = number_format($transactionAmount, 2, '.', '');
        $paidAt = filled($dateApproved) ? Carbon::parse((string) $dateApproved) : now();

        try {
            $markWebOrderAsPaid->execute(
                order: $order,
                paymentId: $paymentId,
                paidAmount: $formattedAmount,
                paidAt: $paidAt,
            );
        } catch (\InvalidArgumentException $exception) {
            Log::error("Mercado Pago webhook: validation failed for order {$order->id}: {$exception->getMessage()}");

            return response()->json(['error' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            Log::error("Mercado Pago webhook: error processing payment for order {$order->id}: {$exception->getMessage()}");

            return response()->json(['error' => 'internal_processing_error'], 500);
        }

        return response()->json(['status' => 'ok', 'order_status' => WebOrderStatus::Paid->value]);
    }
}
