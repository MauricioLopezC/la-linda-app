<?php

namespace App\Http\Controllers\Ecommerce;

use App\Actions\Ecommerce\CreateMercadoPagoPreference;
use App\Actions\Ecommerce\GetCustomerCart;
use App\Actions\Ecommerce\PlaceWebOrder;
use App\Data\Ecommerce\PickupBranchOptionData;
use App\Enums\Ecommerce\DeliveryMethod;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ecommerce\PlaceWebOrderRequest;
use App\Models\Customers\Customer;
use App\Models\Organization\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    /**
     * Show the order summary with the pickup branches to choose from.
     */
    public function show(Request $request, GetCustomerCart $getCustomerCart): Response|RedirectResponse
    {
        $customer = $this->customerOf($request);

        $cart = $getCustomerCart->execute($customer);

        if ($cart->lines_count === 0) {
            return redirect()->route('tienda.cart.index')
                ->with('info', 'Tu carrito está vacío: agregá artículos antes de confirmar el pedido.');
        }

        $branches = Branch::query()->active()->orderBy('name')->get();
        $shippingCost = number_format((float) config('ecommerce.shipping_cost', '2500.00'), 2, '.', '');

        return Inertia::render('ecommerce/checkout/show', [
            'cart' => $cart,
            'branches' => PickupBranchOptionData::collect($branches),
            'default_shipping_address' => $customer->address,
            'shipping_cost' => $shippingCost,
            'formatted_shipping_cost' => '$ '.number_format((float) $shippingCost, 2, ',', '.'),
        ]);
    }

    /**
     * Place the order from the customer's cart and redirect to Mercado Pago.
     */
    public function store(
        PlaceWebOrderRequest $request,
        PlaceWebOrder $placeWebOrder,
        CreateMercadoPagoPreference $createPreference,
    ): SymfonyResponse {
        $customer = $this->customerOf($request);

        $deliveryMethod = DeliveryMethod::from((string) $request->validated('delivery_method'));
        $pickupBranch = $deliveryMethod === DeliveryMethod::Pickup && $request->filled('pickup_branch_id')
            ? Branch::findOrFail((int) $request->validated('pickup_branch_id'))
            : null;

        $order = $placeWebOrder->execute(
            customer: $customer,
            deliveryMethod: $deliveryMethod,
            pickupBranch: $pickupBranch,
            shippingAddress: $request->validated('shipping_address'),
            shippingNotes: $request->validated('shipping_notes'),
            notes: $request->validated('notes'),
        );

        try {
            $preference = $createPreference->execute($order);

            if ($order->fresh()->status === WebOrderStatus::Paid) {
                return redirect()->route('tienda.orders.show', $order)
                    ->with('info', 'El pedido ya se encuentra pagado.');
            }

            return Inertia::location($preference['redirect_url']);
        } catch (\Throwable $exception) {
            if ($order->fresh()->status === WebOrderStatus::Paid) {
                return redirect()->route('tienda.orders.show', $order)
                    ->with('info', 'El pedido ya se encuentra pagado.');
            }

            report($exception);

            return redirect()->route('tienda.orders.show', $order)
                ->with('success', "Confirmamos tu pedido N.º {$order->formattedNumber()}. Podés realizar el pago cuando desees desde aquí.");
        }
    }

    private function customerOf(Request $request): Customer
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->customer === null) {
            abort(403, 'Solo los clientes registrados pueden confirmar pedidos.');
        }

        return $user->customer;
    }
}
