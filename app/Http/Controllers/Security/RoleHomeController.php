<?php

declare(strict_types=1);

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoleHomeController extends Controller
{
    /**
     * Send the user to their role's main screen (HU-004). Gives the frontend one fixed URL for
     * "home" (logo, breadcrumbs) that never lands a role on a module it cannot open.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->role->homeRoute());
    }
}
