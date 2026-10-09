<?php

namespace App\Http\Controllers\Ecommerce;

use App\Actions\Ecommerce\CreateMercadoPagoPreference;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\WebOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class WebOrderPaymentController extends Controller
{
    /**
     * Retry or initiate payment for a pending online order.
     */
    public function store(
        Request $request,
        WebOrder $webOrder,
        CreateMercadoPagoPreference $createPreference,
    ): Response {
        $customer = $this->customerOf($request);

        if ($webOrder->customer_id !== $customer->id) {
            abort(403, 'No estás autorizado para pagar este pedido.');
        }

        if ($webOrder->status === WebOrderStatus::Paid) {
            return redirect()->route('tienda.orders.show', $webOrder)
                ->with('info', 'El pedido ya se encuentra pagado.');
        }

        try {
            $preference = $createPreference->execute($webOrder);

            return Inertia::location($preference['redirect_url']);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('tienda.orders.show', $webOrder)
                ->with('error', 'No pudimos conectar con Mercado Pago en este momento. Por favor intentá nuevamente más tarde.');
        }
    }

    private function customerOf(Request $request): Customer
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->customer === null) {
            abort(403, 'Solo los clientes registrados pueden realizar pagos.');
        }

        return $user->customer;
    }
}
