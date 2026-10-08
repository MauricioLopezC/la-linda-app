<?php

namespace App\Http\Controllers\Ecommerce;

use App\Data\Ecommerce\WebOrderData;
use App\Data\Ecommerce\WebOrderListData;
use App\Http\Controllers\Controller;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\WebOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebOrderController extends Controller
{
    /**
     * List the customer's own orders, newest first.
     */
    public function index(Request $request): Response
    {
        $customer = $this->customerOf($request);

        $orders = WebOrder::query()
            ->whereBelongsTo($customer)
            ->withCount('items')
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('ecommerce/orders/index', [
            'orders' => WebOrderListData::collect($orders),
        ]);
    }

    /**
     * Show the read-only detail of one of the customer's orders.
     */
    public function show(Request $request, WebOrder $webOrder): Response
    {
        $customer = $this->customerOf($request);

        if ($webOrder->customer_id !== $customer->id) {
            abort(403, 'No estás autorizado para ver este pedido.');
        }

        $webOrder->load(['pickupBranch', 'items.article.unitOfMeasure', 'items.priceList']);

        return Inertia::render('ecommerce/orders/show', [
            'order' => WebOrderData::fromModel($webOrder),
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
