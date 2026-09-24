<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\AddArticleToSale;
use App\Actions\Sales\ChangeSaleCustomer;
use App\Actions\Sales\DiscardSale;
use App\Actions\Sales\OpenSale;
use App\Actions\Sales\RemoveSaleItem;
use App\Actions\Sales\UpdateSaleItemQuantity;
use App\Data\Sales\PointOfSaleData;
use App\Data\Sales\SaleArticleOptionData;
use App\Data\Sales\SaleCustomerOptionData;
use App\Data\Sales\SaleData;
use App\Data\Sales\SaleListData;
use App\Enums\Sales\SaleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSaleItemRequest;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Http\Requests\Sales\UpdateSaleCustomerRequest;
use App\Http\Requests\Sales\UpdateSaleItemRequest;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
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
            ->withCount('items')
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
     * Search active articles by internal code, barcode or description.
     */
    public function searchArticles(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $query = Article::query()->active()->with('unitOfMeasure');

        if ($search !== '') {
            $lower = mb_strtolower($search);
            $query->where(function (Builder $q) use ($lower) {
                $q->whereRaw('LOWER(description) LIKE ?', ["%{$lower}%"])
                    ->orWhereRaw('LOWER(internal_code) LIKE ?', ["%{$lower}%"])
                    ->orWhereRaw('LOWER(barcode) LIKE ?', ["%{$lower}%"]);
            });
        }

        $articles = $query->orderBy('description')->limit(20)->get();

        return response()->json(SaleArticleOptionData::collect($articles));
    }

    /**
     * Display the sale screen.
     */
    public function show(Sale $sale): Response
    {
        return Inertia::render('sales/sales/show', [
            'sale' => SaleData::fromModel($sale),
            'customers' => SaleCustomerOptionData::collect($this->activeCustomers()),
        ]);
    }

    /**
     * Add an article to the sale, by id or by scanned code.
     */
    public function storeItem(StoreSaleItemRequest $request, Sale $sale, AddArticleToSale $action): RedirectResponse
    {
        $action->handle($sale, $request->validated());

        return back();
    }

    /**
     * Change the quantity of a sale line.
     */
    public function updateItem(UpdateSaleItemRequest $request, Sale $sale, SaleItem $item, UpdateSaleItemQuantity $action): RedirectResponse
    {
        $action->handle($item, (float) $request->validated('quantity'));

        return back();
    }

    /**
     * Remove a line from the sale.
     */
    public function destroyItem(Sale $sale, SaleItem $item, RemoveSaleItem $action): RedirectResponse
    {
        $action->handle($item);

        return back();
    }

    /**
     * Change the sale's customer, re-pricing its lines.
     */
    public function updateCustomer(UpdateSaleCustomerRequest $request, Sale $sale, ChangeSaleCustomer $action): RedirectResponse
    {
        $action->handle($sale, Customer::findOrFail((int) $request->validated('customer_id')));

        return back()->with('success', 'Cliente actualizado y precios recalculados.');
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
