<?php

namespace App\Http\Controllers\Ecommerce;

use App\Actions\Ecommerce\UpdateCustomerContactInfo;
use App\Data\Ecommerce\CustomerProfileData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ecommerce\UpdateCustomerContactRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAccountController extends Controller
{
    /**
     * Show client account and contact information.
     */
    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('ecommerce/account/show', [
            'customer' => CustomerProfileData::fromUser($user),
        ]);
    }

    /**
     * Update client contact information.
     */
    public function update(
        UpdateCustomerContactRequest $request,
        UpdateCustomerContactInfo $action
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $action->handle($user, $request->validated());

        return redirect()->route('tienda.account.show')->with('success', 'Tus datos de contacto han sido actualizados.');
    }
}
