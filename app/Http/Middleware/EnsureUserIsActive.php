<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && ! Auth::user()->is_active) {
            return $this->logoutAndRedirect($request);
        }

        $response = $next($request);

        if (Auth::check() && ! Auth::user()->is_active) {
            return $this->logoutAndRedirect($request);
        }

        return $response;
    }

    private function logoutAndRedirect(Request $request): Response
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            abort(Response::HTTP_FORBIDDEN, 'Su cuenta ha sido desactivada. Comuníquese con el administrador.');
        }

        return redirect()->route('login')->withErrors([
            'email' => 'Su cuenta ha sido desactivada. Comuníquese con el administrador.',
        ]);
    }
}
