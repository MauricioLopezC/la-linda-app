<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\CancelPurchaseOrder;
use App\Actions\Purchasing\CreatePurchaseOrder;
use App\Actions\Purchasing\IssuePurchaseOrder;
use App\Actions\Purchasing\UpdatePurchaseOrder;
use App\Data\Purchasing\PurchaseOrderArticleOptionData;
use App\Data\Purchasing\PurchaseOrderData;
use App\Data\Purchasing\PurchaseOrderListData;
use App\Data\Purchasing\PurchaseOrderWarehouseOptionData;
use App\Data\Purchasing\SupplierOptionData;
use App\Enums\Purchasing\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\CancelPurchaseOrderRequest;
use App\Http\Requests\Purchasing\StorePurchaseOrderRequest;
use App\Http\Requests\Purchasing\UpdatePurchaseOrderRequest;
use App\Models\Catalog\Article;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\Supplier;
use App\Rules\Purchasing\ValidCuit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier:id,business_name', 'warehouse:id,name'])
            ->withCount('items')
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = trim((string) $request->input('search'));
                $lower = mb_strtolower($search);
                $query->where(function (Builder $q) use ($search, $lower) {
                    $q->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function (Builder $sq) use ($lower) {
                            $sq->whereRaw('LOWER(business_name) LIKE ?', ["%{$lower}%"]);
                        });
                });
            })
            ->when($request->filled('supplier_id') && $request->input('supplier_id') !== 'all', function (Builder $query) use ($request) {
                $query->where('supplier_id', $request->input('supplier_id'));
            })
            ->when($request->filled('warehouse_id') && $request->input('warehouse_id') !== 'all', function (Builder $query) use ($request) {
                $query->where('warehouse_id', $request->input('warehouse_id'));
            })
            ->when($request->filled('status') && $request->input('status') !== 'all', function (Builder $query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->when($request->filled('date_from'), function (Builder $query) use ($request) {
                $query->whereDate('issue_date', '>=', $request->input('date_from'));
            })
            ->when($request->filled('date_to'), function (Builder $query) use ($request) {
                $query->whereDate('issue_date', '<=', $request->input('date_to'));
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $suppliers = Supplier::query()->active()->orderBy('business_name')->get();
        $warehouses = Warehouse::query()->active()->orderBy('name')->get();

        return Inertia::render('purchasing/orders/index', [
            'orders' => PurchaseOrderListData::collect($orders),
            'suppliers' => SupplierOptionData::collect($suppliers),
            'warehouses' => PurchaseOrderWarehouseOptionData::collect($warehouses),
            'statuses' => PurchaseOrderStatus::toOptions(),
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'supplier_id' => (string) $request->input('supplier_id', 'all'),
                'warehouse_id' => (string) $request->input('warehouse_id', 'all'),
                'status' => (string) $request->input('status', 'all'),
                'date_from' => (string) $request->input('date_from', ''),
                'date_to' => (string) $request->input('date_to', ''),
            ],
        ]);
    }

    public function create(): Response
    {
        $suppliers = Supplier::query()->active()->orderBy('business_name')->get();
        $warehouses = Warehouse::query()->active()->orderBy('name')->get();
        $articles = Article::query()->active()->with('unitOfMeasure')->orderBy('description')->get();

        return Inertia::render('purchasing/orders/form', [
            'order' => null,
            'suppliers' => SupplierOptionData::collect($suppliers),
            'warehouses' => PurchaseOrderWarehouseOptionData::collect($warehouses),
            'articles' => PurchaseOrderArticleOptionData::collect($articles),
            'today' => today()->toDateString(),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request, CreatePurchaseOrder $action): RedirectResponse
    {
        $order = $action->handle($request->validated());

        return to_route('purchasing.orders.show', $order)
            ->with('success', 'Orden de compra creada correctamente.');
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        return Inertia::render('purchasing/orders/show', [
            'order' => PurchaseOrderData::fromModel($purchaseOrder),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): Response|RedirectResponse
    {
        if (! $purchaseOrder->canBeEdited()) {
            return to_route('purchasing.orders.show', $purchaseOrder)
                ->with('error', 'Solo las órdenes en estado borrador pueden editarse.');
        }

        $suppliers = Supplier::query()->active()->orderBy('business_name')->get();
        $warehouses = Warehouse::query()->active()->orderBy('name')->get();
        $articles = Article::query()->active()->with('unitOfMeasure')->orderBy('description')->get();

        return Inertia::render('purchasing/orders/form', [
            'order' => PurchaseOrderData::fromModel($purchaseOrder),
            'suppliers' => SupplierOptionData::collect($suppliers),
            'warehouses' => PurchaseOrderWarehouseOptionData::collect($warehouses),
            'articles' => PurchaseOrderArticleOptionData::collect($articles),
            'today' => today()->toDateString(),
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, UpdatePurchaseOrder $action): RedirectResponse
    {
        $action->handle($purchaseOrder, $request->validated());

        return to_route('purchasing.orders.show', $purchaseOrder)
            ->with('success', 'Orden de compra actualizada correctamente.');
    }

    public function issue(PurchaseOrder $purchaseOrder, IssuePurchaseOrder $action): RedirectResponse
    {
        $action->handle($purchaseOrder);

        return back()->with('success', 'Orden de compra emitida correctamente.');
    }

    public function cancel(CancelPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, CancelPurchaseOrder $action): RedirectResponse
    {
        $action->handle($purchaseOrder, (string) $request->input('reason'));

        return back()->with('success', 'Orden de compra cancelada correctamente.');
    }

    public function pdf(PurchaseOrder $purchaseOrder): HttpResponse
    {
        $purchaseOrder->loadMissing(['supplier', 'warehouse', 'items.article.unitOfMeasure', 'user']);

        $filename = 'orden-de-compra-'.$purchaseOrder->order_number.'.pdf';
        $cssPath = resource_path('css/pdf/purchase-order.css');
        $stylesheet = File::exists($cssPath) ? File::get($cssPath) : '';

        return Pdf::loadView('pdf.purchasing.purchase-order', [
            'order' => $purchaseOrder,
            'supplierTaxId' => ValidCuit::format($purchaseOrder->supplier->tax_id) ?? $purchaseOrder->supplier->tax_id,
            'company' => config('company'),
            'stylesheet' => $stylesheet,
        ])->setPaper('a4')->download($filename);
    }
}
