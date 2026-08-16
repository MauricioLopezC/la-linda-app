<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\CreatePointOfSale;
use App\Actions\Sales\TogglePointOfSaleStatus;
use App\Actions\Sales\UpdatePointOfSale;
use App\Data\Inventory\WarehouseData;
use App\Data\Sales\PointOfSaleData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StorePointOfSaleRequest;
use App\Http\Requests\Sales\UpdatePointOfSaleRequest;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\PointOfSale;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PointOfSaleController extends Controller
{
    /**
     * Display a listing of points of sale.
     */
    public function index(): Response
    {
        $pointsOfSale = PointOfSale::query()->with('warehouse.branch')->orderBy('number')->get();
        $warehouses = Warehouse::query()->with('branch')->orderBy('name')->get();

        return Inertia::render('sales/points-of-sale/index', [
            'pointsOfSale' => PointOfSaleData::collect($pointsOfSale),
            'warehouses' => WarehouseData::collect($warehouses),
        ]);
    }

    /**
     * Store a newly created point of sale.
     */
    public function store(StorePointOfSaleRequest $request, CreatePointOfSale $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Punto de venta creado correctamente.');
    }

    /**
     * Update the specified point of sale.
     */
    public function update(UpdatePointOfSaleRequest $request, PointOfSale $pointOfSale, UpdatePointOfSale $action): RedirectResponse
    {
        $action->handle($pointOfSale, $request->validated());

        return back()->with('success', 'Punto de venta actualizado correctamente.');
    }

    /**
     * Toggle the active status of the specified point of sale.
     */
    public function toggleStatus(PointOfSale $pointOfSale, TogglePointOfSaleStatus $action): RedirectResponse
    {
        $action->handle($pointOfSale);

        return back()->with('success', 'Estado del punto de venta actualizado.');
    }
}
