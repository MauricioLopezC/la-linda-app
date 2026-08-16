<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\CreateWarehouse;
use App\Actions\Inventory\ToggleWarehouseStatus;
use App\Actions\Inventory\UpdateWarehouse;
use App\Data\Inventory\WarehouseData;
use App\Data\Organization\BranchData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreWarehouseRequest;
use App\Http\Requests\Inventory\UpdateWarehouseRequest;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    /**
     * Display a listing of warehouses.
     */
    public function index(): Response
    {
        $warehouses = Warehouse::query()->with('branch')->orderBy('name')->get();
        $branches = Branch::query()->orderBy('name')->get();

        return Inertia::render('inventory/warehouses/index', [
            'warehouses' => WarehouseData::collect($warehouses),
            'branches' => BranchData::collect($branches),
        ]);
    }

    /**
     * Store a newly created warehouse.
     */
    public function store(StoreWarehouseRequest $request, CreateWarehouse $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Depósito creado correctamente.');
    }

    /**
     * Update the specified warehouse.
     */
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse, UpdateWarehouse $action): RedirectResponse
    {
        $action->handle($warehouse, $request->validated());

        return back()->with('success', 'Depósito actualizado correctamente.');
    }

    /**
     * Toggle the active status of the specified warehouse.
     */
    public function toggleStatus(Warehouse $warehouse, ToggleWarehouseStatus $action): RedirectResponse
    {
        $action->handle($warehouse);

        return back()->with('success', 'Estado del depósito actualizado.');
    }
}
