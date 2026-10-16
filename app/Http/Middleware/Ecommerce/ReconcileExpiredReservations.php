<?php

namespace App\Http\Middleware\Ecommerce;

use App\Actions\Ecommerce\ReconcileExpiredWebOrderReservations;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

use function Illuminate\Support\defer;

/**
 * The app hibernates when idle and runs no scheduler, so store traffic is what releases expired
 * stock reservations: at most once per interval, after the response has been sent.
 */
class ReconcileExpiredReservations
{
    public const string THROTTLE_KEY = 'ecommerce:reservations:on-demand-reconciliation';

    private const int INTERVAL_SECONDS = 60;

    public function __construct(private readonly ReconcileExpiredWebOrderReservations $reconcileExpired) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (Cache::add(self::THROTTLE_KEY, true, self::INTERVAL_SECONDS)) {
            defer(fn () => $this->reconcileExpired->execute());
        }

        return $response;
    }
}
