<?php

namespace App\Http\Controllers\Ecommerce;

use App\Actions\Ecommerce\ProcessMercadoPagoPaymentNotification;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class MercadoPagoWebhookController extends Controller
{
    /**
     * Handle incoming notifications from Mercado Pago webhook.
     */
    public function __invoke(
        Request $request,
        ProcessMercadoPagoPaymentNotification $processNotification,
    ): JsonResponse {
        $type = $request->input('type') ?? $request->input('topic') ?? $request->query('topic');
        $paymentId = $request->input('data.id') ?? $request->input('id') ?? $request->query('id');

        // Gracefully ignore non-payment notifications or notifications without an id
        if ($type !== 'payment' && $type !== null && ! str_contains((string) $type, 'payment')) {
            return response()->json(['status' => 'ignored', 'reason' => 'non_payment_event']);
        }

        if (blank($paymentId)) {
            return response()->json(['status' => 'ignored', 'reason' => 'missing_payment_id']);
        }

        try {
            $result = $processNotification->execute((string) $paymentId);

            return response()->json($result['data'], $result['status_code']);
        } catch (InvalidArgumentException $exception) {
            Log::error("Mercado Pago webhook: validation failed for payment {$paymentId}: {$exception->getMessage()}");

            return response()->json(['error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Log::error("Mercado Pago webhook: error processing payment {$paymentId}: {$exception->getMessage()}");

            return response()->json(['error' => 'mercadopago_api_error'], 500);
        }
    }
}
