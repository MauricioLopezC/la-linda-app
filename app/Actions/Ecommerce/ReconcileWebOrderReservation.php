<?php

namespace App\Actions\Ecommerce;

use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Catalog\Article;
use App\Models\Ecommerce\WebOrder;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ReconcileWebOrderReservation
{
    public function __construct(private readonly ProcessMercadoPagoPaymentNotification $processPayment) {}

    /**
     * An expired reservation still counts until all its checkouts are closed and its payments
     * have a definitive outcome. Remote errors leave the reservation intact for the next run.
     */
    public function execute(WebOrder $order): bool
    {
        $order = $order->fresh() ?? $order;

        if ($order->status !== WebOrderStatus::Pending || $order->stock_reserved_until === null
            || $order->stock_reserved_until->isFuture()) {
            return false;
        }

        $reference = $order->payment_reservation_token === null
            ? (string) $order->id : "{$order->id}:{$order->payment_reservation_token}";
        $preferenceIds = $this->findPreferenceIds($reference);

        if ($order->mp_preference_id !== null) {
            $preferenceIds[] = $order->mp_preference_id;
        }

        foreach (array_unique($preferenceIds) as $preferenceId) {
            /** @var array{expires?: bool, expiration_date_to?: string} $closed */
            $closed = $this->request()->put('/checkout/preferences/'.rawurlencode($preferenceId), [
                'expires' => true,
                'expiration_date_from' => $order->stock_reserved_until->copy()->subDay()->toIso8601String(),
                'expiration_date_to' => $order->stock_reserved_until->toIso8601String(),
            ])->throw()->json();

            if (($closed['expires'] ?? false) !== true || empty($closed['expiration_date_to'])
                || Carbon::parse($closed['expiration_date_to'])->isFuture()) {
                throw new RuntimeException('Mercado Pago no confirmó el cierre del checkout; la reserva se conserva.');
            }
        }

        if (! $this->paymentsAreClosed($reference, $order)) {
            return false;
        }

        return DB::transaction(function () use ($order): bool {
            $lockedOrder = WebOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status !== WebOrderStatus::Pending
                || $lockedOrder->payment_reservation_token !== $order->payment_reservation_token
                || $lockedOrder->stock_reserved_until?->toIso8601String() !== $order->stock_reserved_until->toIso8601String()) {
                return false;
            }

            $articleIds = $lockedOrder->items()->pluck('article_id')->all();
            Article::query()->whereIn('id', $articleIds)->orderBy('id')->lockForUpdate()->get();
            $lockedOrder->update([
                'stock_reserved_until' => null,
                'payment_reservation_token' => null,
                'mp_preference_id' => null,
                'mp_init_point' => null,
                'mp_sandbox_init_point' => null,
            ]);

            return true;
        });
    }

    /** @return list<string> */
    private function findPreferenceIds(string $reference): array
    {
        $ids = [];
        $offset = 0;

        do {
            /** @var array{elements?: list<array{id?: string}>, next_offset?: int, total?: int} $page */
            $page = $this->request()->get('/checkout/preferences/search', [
                'external_reference' => $reference, 'limit' => 50, 'offset' => $offset,
            ])->throw()->json();

            if (! isset($page['elements'], $page['total'])) {
                throw new RuntimeException('Respuesta de búsqueda de checkouts incompleta; la reserva se conserva.');
            }

            foreach ($page['elements'] as $preference) {
                if (empty($preference['id'])) {
                    throw new RuntimeException('Checkout sin identificador; la reserva se conserva.');
                }

                $ids[] = $preference['id'];
            }

            $nextOffset = $page['next_offset'] ?? $offset + count($page['elements']);
            if ($nextOffset <= $offset && count($ids) < $page['total']) {
                throw new RuntimeException('Paginación de checkouts incompleta; la reserva se conserva.');
            }
            $offset = $nextOffset;
        } while ($offset < $page['total']);

        return $ids;
    }

    private function paymentsAreClosed(string $reference, WebOrder $order): bool
    {
        $offset = 0;
        $allClosed = true;

        do {
            /** @var array{results?: list<array{id?: int|string, status?: string, external_reference?: string}>, paging?: array{total?: int}} $page */
            $page = $this->request()->get('/v1/payments/search', [
                'external_reference' => $reference, 'limit' => 50, 'offset' => $offset,
                'range' => 'date_created', 'begin_date' => $order->placed_at->toIso8601String(),
                'end_date' => now()->toIso8601String(),
            ])->throw()->json();

            if (! isset($page['results'], $page['paging']['total'])) {
                throw new RuntimeException('Respuesta de búsqueda de pagos incompleta; la reserva se conserva.');
            }

            foreach ($page['results'] as $payment) {
                if (($payment['external_reference'] ?? '') !== $reference || empty($payment['id'])) {
                    throw new RuntimeException('Pago sin referencia verificable; la reserva se conserva.');
                }

                if (($payment['status'] ?? '') === 'approved') {
                    $this->processPayment->execute((string) $payment['id']);
                    $allClosed = false;
                } elseif (! in_array($payment['status'] ?? '', ['rejected', 'cancelled'], true)) {
                    $allClosed = false;
                }
            }

            $count = count($page['results']);
            $offset += $count;

            if ($count === 0 && $offset < $page['paging']['total']) {
                throw new RuntimeException('Paginación de pagos incompleta; la reserva se conserva.');
            }
        } while ($offset < $page['paging']['total']);

        return $allClosed;
    }

    private function request(): PendingRequest
    {
        $token = (string) config('services.mercadopago.access_token');

        if ($token === '') {
            throw new RuntimeException('El token de acceso de Mercado Pago no está configurado.');
        }

        return Http::baseUrl((string) config('services.mercadopago.base_url', 'https://api.mercadopago.com'))
            ->withToken($token)->acceptJson()->timeout(10)->connectTimeout(3);
    }
}
