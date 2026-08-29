<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\CreateStockMovementType;
use App\Actions\Inventory\DeleteStockMovementType;
use App\Actions\Inventory\UpdateStockMovementType;
use App\Data\Inventory\StockMovementTypeData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockMovementTypeRequest;
use App\Http\Requests\Inventory\UpdateStockMovementTypeRequest;
use App\Models\Inventory\StockMovementType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StockParameterController extends Controller
{
    /**
     * Display a listing of stock movement types.
     */
    public function index(): Response
    {
        $movementTypes = StockMovementType::query()
            ->withExists('stockMovements')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return Inertia::render('inventory/parameters/index', [
            'movementTypes' => StockMovementTypeData::collect($movementTypes),
        ]);
    }

    /**
     * Store a newly created stock movement type.
     */
    public function storeMovementType(
        StoreStockMovementTypeRequest $request,
        CreateStockMovementType $action
    ): RedirectResponse {
        $action->handle($request->validated());

        return back()->with('success', 'Tipo de movimiento de stock creado correctamente.');
    }

    /**
     * Update the specified stock movement type.
     */
    public function updateMovementType(
        UpdateStockMovementTypeRequest $request,
        StockMovementType $movementType,
        UpdateStockMovementType $action
    ): RedirectResponse {
        $action->handle($movementType, $request->validated());

        return back()->with('success', 'Tipo de movimiento de stock actualizado correctamente.');
    }

    /**
     * Remove the specified stock movement type from storage.
     */
    public function destroyMovementType(
        StockMovementType $movementType,
        DeleteStockMovementType $action
    ): RedirectResponse {
        $action->handle($movementType);

        return back()->with('success', 'Tipo de movimiento de stock eliminado correctamente.');
    }
}
