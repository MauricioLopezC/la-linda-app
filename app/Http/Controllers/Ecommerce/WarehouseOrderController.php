<?php

namespace App\Http\Controllers\Ecommerce;

use App\Actions\Ecommerce\DeliverWebOrder;
use App\Actions\Ecommerce\ResolveWebOrderWarehouse;
use App\Actions\Ecommerce\StartWebOrderPreparation;
use App\Data\Ecommerce\WarehouseWebOrderDetailData;
use App\Data\Ecommerce\WarehouseWebOrderListData;
use App\Enums\Ecommerce\DeliveryMethod;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ecommerce\WarehouseOrderFilterRequest;
use App\Models\Ecommerce\WebOrder;
use App\Models\Organization\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseOrderController extends Controller
{
    /**
     * Display the warehouse orders inbox with status counters and filters.
     */
    public function index(WarehouseOrderFilterRequest $request): Response
    {
        $counts = [
            'paid' => WebOrder::query()->where('status', WebOrderStatus::Paid)->count(),
            'in_preparation' => WebOrder::query()->where('status', WebOrderStatus::InPreparation)->count(),
            'delivered' => WebOrder::query()->where('status', WebOrderStatus::Delivered)->count(),
        ];

        $query = WebOrder::query()
            ->whereIn('status', [WebOrderStatus::Paid, WebOrderStatus::InPreparation, WebOrderStatus::Delivered])
            ->with(['customer', 'pickupBranch', 'preparedBy', 'deliveredBy', 'sale'])
            ->withCount('items');

        $status = $request->input('status', 'todos');
        if ($status && $status !== 'todos') {
            $query->where('status', $status);
        }

        $deliveryMethod = $request->input('delivery_method', 'todos');
        if ($deliveryMethod && $deliveryMethod !== 'todos') {
            $query->where('delivery_method', $deliveryMethod);
        }

        if ($request->filled('pickup_branch_id')) {
            $query->where('pickup_branch_id', $request->input('pickup_branch_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('paid_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('paid_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $lowerSearch = mb_strtolower($search);
            $numberSearch = ltrim($search, '#0');

            $query->where(function (Builder $q) use ($search, $lowerSearch, $numberSearch) {
                if ($numberSearch !== '' && is_numeric($numberSearch)) {
                    $q->where('number', (int) $numberSearch)
                        ->orWhereHas('sale', fn (Builder $saleQuery) => $saleQuery->whereKey((int) $numberSearch));
                }

                $q->orWhereHas('customer', function (Builder $cq) use ($lowerSearch, $search) {
                    $cq->whereRaw('LOWER(name) LIKE ?', ["%{$lowerSearch}%"])
                        ->orWhere('id_number', 'like', "%{$search}%");
                })->orWhereRaw('LOWER(shipping_address) LIKE ?', ["%{$lowerSearch}%"]);
            });
        }

        $orders = $query
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('ecommerce/warehouse-orders/index', [
            'orders' => WarehouseWebOrderListData::collect($orders),
            'counts' => $counts,
            'filters' => [
                'status' => $status,
                'delivery_method' => $deliveryMethod,
                'pickup_branch_id' => $request->input('pickup_branch_id') ? (int) $request->input('pickup_branch_id') : null,
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'search' => $request->input('search'),
            ],
            'branches' => $branches,
        ]);
    }

    /**
     * Display the warehouse picking sheet and order details.
     */
    public function show(WebOrder $webOrder, ResolveWebOrderWarehouse $resolveWarehouse): Response
    {
        $webOrder->load([
            'customer',
            'pickupBranch',
            'preparedBy',
            'deliveredBy',
            'sale.pointOfSale.warehouse',
            'sale.stockMovement.type',
            'sale.stockMovement.warehouse',
            'items.article.unitOfMeasure',
        ]);

        return Inertia::render('ecommerce/warehouse-orders/show', [
            'order' => WarehouseWebOrderDetailData::fromModel($webOrder, $resolveWarehouse->execute($webOrder)),
        ]);
    }

    /**
     * Start order preparation in warehouse.
     */
    public function startPreparation(Request $request, WebOrder $webOrder, StartWebOrderPreparation $action): RedirectResponse
    {
        $action->execute($webOrder, $request->user());

        return back()->with('success', "Se inició la preparación del pedido #{$webOrder->formattedNumber()}.");
    }

    /**
     * Deliver or dispatch order from warehouse with stock deduction.
     */
    public function deliver(Request $request, WebOrder $webOrder, DeliverWebOrder $action): RedirectResponse
    {
        $action->execute($webOrder, $request->user());

        $actionLabel = $webOrder->delivery_method === DeliveryMethod::Shipping ? 'despachó' : 'entregó';

        return back()->with('success', "Se {$actionLabel} el pedido #{$webOrder->formattedNumber()} y se registró la baja de stock.");
    }
}
