<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->isClient()) {
            return redirect()->intended(route('tienda.home'));
        }

        return redirect()->intended('/dashboard');
    }
}
