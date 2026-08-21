<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\CreatePaymentMethod;
use App\Actions\Sales\TogglePaymentMethodStatus;
use App\Actions\Sales\UpdatePaymentMethod;
use App\Data\Sales\PaymentMethodData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StorePaymentMethodRequest;
use App\Http\Requests\Sales\UpdatePaymentMethodRequest;
use App\Models\Sales\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function index(): Response
    {
        $paymentMethods = PaymentMethod::query()->orderBy('name')->get();

        return Inertia::render('sales/payment-methods/index', [
            'paymentMethods' => PaymentMethodData::collect($paymentMethods),
        ]);
    }

    public function store(StorePaymentMethodRequest $request, CreatePaymentMethod $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Medio de pago creado correctamente.');
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod, UpdatePaymentMethod $action): RedirectResponse
    {
        $action->handle($paymentMethod, $request->validated());

        return back()->with('success', 'Medio de pago actualizado correctamente.');
    }

    public function toggleStatus(PaymentMethod $paymentMethod, TogglePaymentMethodStatus $action): RedirectResponse
    {
        $action->handle($paymentMethod);

        return back()->with('success', 'Estado del medio de pago actualizado.');
    }
}
