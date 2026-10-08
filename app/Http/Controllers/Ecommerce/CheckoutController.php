<?php

namespace App\Http\Controllers\Ecommerce;

use App\Actions\Ecommerce\GetCustomerCart;
use App\Actions\Ecommerce\PlaceWebOrder;
use App\Data\Ecommerce\PickupBranchOptionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ecommerce\PlaceWebOrderRequest;
use App\Models\Customers\Customer;
use App\Models\Organization\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

        return Inertia::render('ecommerce/checkout/show', [
            'cart' => $cart,
            'branches' => PickupBranchOptionData::collect($branches),
        ]);
    }

    /**
     * Place the order from the customer's cart.
     */
    public function store(PlaceWebOrderRequest $request, PlaceWebOrder $placeWebOrder): RedirectResponse
    {
        $customer = $this->customerOf($request);

        /** @var Branch $branch */
        $branch = Branch::findOrFail($request->validated('pickup_branch_id'));

        $order = $placeWebOrder->execute($customer, $branch, $request->validated('notes'));

        return redirect()->route('tienda.orders.show', $order)
            ->with('success', "Confirmamos tu pedido N.º {$order->formattedNumber()}.");
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
