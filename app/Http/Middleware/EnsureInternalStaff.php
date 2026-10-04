<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalStaff
{
    /**
     * Handle an incoming request.
     *
     * Ensures that store clients cannot access internal ERP management panel routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        if ($user->isClient()) {
            if ($request->expectsJson()) {
                abort(Response::HTTP_FORBIDDEN, 'Acceso denegado: tu cuenta de cliente no tiene permisos para acceder al panel interno.');
            }

            return redirect()->route('tienda.home');
        }

        return $next($request);
    }
}
