<?php

namespace App\Http\Controllers\Ecommerce;

use App\Data\Ecommerce\WebOrderData;
use App\Http\Controllers\Controller;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\WebOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebOrderPaymentReturnController extends Controller
{
    /**
     * Display information page when returning from Mercado Pago checkout.
     * Strictly informational: does not mutate order payment status.
     */
    public function show(Request $request, WebOrder $webOrder): Response
    {
        $customer = $this->customerOf($request);

        if ($webOrder->customer_id !== $customer->id) {
            abort(403, 'No estás autorizado para ver este pedido.');
        }

        $webOrder->refresh();
        $webOrder->load(['pickupBranch', 'items.article.unitOfMeasure', 'items.priceList']);

        $rawStatus = (string) ($request->query('status') ?? $request->query('collection_status') ?? 'pending');
        $normalizedStatus = match ($rawStatus) {
            'approved' => 'approved',
            'rejected', 'cancelled', 'null' => 'rejected',
            default => 'pending',
        };

        return Inertia::render('ecommerce/orders/payment-return', [
            'order' => WebOrderData::fromModel($webOrder),
            'status' => $normalizedStatus,
        ]);
    }

    private function customerOf(Request $request): Customer
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->customer === null) {
            abort(403, 'Solo los clientes registrados pueden consultar sus pedidos.');
        }

        return $user->customer;
    }
}
