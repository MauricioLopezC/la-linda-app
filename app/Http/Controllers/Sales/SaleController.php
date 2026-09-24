<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\DiscardSale;
use App\Actions\Sales\OpenSale;
use App\Data\Sales\PointOfSaleData;
use App\Data\Sales\SaleCustomerOptionData;
use App\Data\Sales\SaleData;
use App\Data\Sales\SaleListData;
use App\Enums\Sales\SaleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Models\Customers\Customer;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    /**
     * Display a listing of sales.
     */
    public function index(Request $request): Response
    {
        $sales = Sale::query()
            ->with(['pointOfSale.warehouse.branch', 'customer', 'user'])
            ->when($request->filled('status') && $request->input('status') !== 'all', function (Builder $query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->when($request->filled('point_of_sale_id') && $request->input('point_of_sale_id') !== 'all', function (Builder $query) use ($request) {
                $query->where('point_of_sale_id', $request->input('point_of_sale_id'));
            })
            ->when($request->filled('date'), function (Builder $query) use ($request) {
                $query->whereDate('opened_at', $request->input('date'));
            })
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $pointsOfSale = PointOfSale::query()->with('warehouse.branch')->orderBy('number')->get();

        return Inertia::render('sales/sales/index', [
            'sales' => SaleListData::collect($sales),
            'pointsOfSale' => PointOfSaleData::collect($pointsOfSale),
            'customers' => SaleCustomerOptionData::collect($this->activeCustomers()),
            'statuses' => SaleStatus::toOptions(),
            'filters' => [
                'status' => (string) $request->input('status', 'all'),
                'point_of_sale_id' => (string) $request->input('point_of_sale_id', 'all'),
                'date' => (string) $request->input('date', ''),
            ],
        ]);
    }

    /**
     * Open a new counter sale.
     */
    public function store(StoreSaleRequest $request, OpenSale $action): RedirectResponse
    {
        $sale = $action->handle($request->validated());

        return to_route('sales.sales.show', $sale)->with('success', 'Venta abierta correctamente.');
    }

    /**
     * Display the sale screen.
     */
    public function show(Sale $sale): Response
    {
        return Inertia::render('sales/sales/show', [
            'sale' => SaleData::fromModel($sale),
        ]);
    }

    /**
     * Discard an open sale.
     */
    public function discard(Sale $sale, DiscardSale $action): RedirectResponse
    {
        $action->handle($sale);

        return to_route('sales.sales.index')->with('success', 'Venta descartada.');
    }

    /**
     * @return Collection<int, Customer>
     */
    private function activeCustomers(): Collection
    {
        return Customer::query()
            ->active()
            ->with('priceList')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }
}
