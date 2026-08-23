<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\CreateStockAdjustmentReason;
use App\Actions\Inventory\CreateStockMovementType;
use App\Actions\Inventory\DeleteStockAdjustmentReason;
use App\Actions\Inventory\DeleteStockMovementType;
use App\Actions\Inventory\ToggleStockAdjustmentReasonStatus;
use App\Actions\Inventory\UpdateStockAdjustmentReason;
use App\Actions\Inventory\UpdateStockMovementType;
use App\Data\Inventory\StockAdjustmentReasonData;
use App\Data\Inventory\StockMovementTypeData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockAdjustmentReasonRequest;
use App\Http\Requests\Inventory\StoreStockMovementTypeRequest;
use App\Http\Requests\Inventory\UpdateStockAdjustmentReasonRequest;
use App\Http\Requests\Inventory\UpdateStockMovementTypeRequest;
use App\Models\Inventory\StockAdjustmentReason;
use App\Models\Inventory\StockMovementType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StockParameterController extends Controller
{
    /**
     * Display a listing of stock movement types and adjustment reasons.
     */
    public function index(): Response
    {
        $movementTypes = StockMovementType::query()
            ->withExists('stockMovements')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        $adjustmentReasons = StockAdjustmentReason::query()
            ->orderBy('name')
            ->get();

        return Inertia::render('inventory/parameters/index', [
            'movementTypes' => StockMovementTypeData::collect($movementTypes),
            'adjustmentReasons' => StockAdjustmentReasonData::collect($adjustmentReasons),
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

    /**
     * Store a newly created stock adjustment reason.
     */
    public function storeAdjustmentReason(
        StoreStockAdjustmentReasonRequest $request,
        CreateStockAdjustmentReason $action
    ): RedirectResponse {
        $action->handle($request->validated());

        return back()->with('success', 'Motivo de ajuste creado correctamente.');
    }

    /**
     * Update the specified stock adjustment reason.
     */
    public function updateAdjustmentReason(
        UpdateStockAdjustmentReasonRequest $request,
        StockAdjustmentReason $adjustmentReason,
        UpdateStockAdjustmentReason $action
    ): RedirectResponse {
        $action->handle($adjustmentReason, $request->validated());

        return back()->with('success', 'Motivo de ajuste actualizado correctamente.');
    }

    /**
     * Remove the specified stock adjustment reason from storage.
     */
    public function destroyAdjustmentReason(
        StockAdjustmentReason $adjustmentReason,
        DeleteStockAdjustmentReason $action
    ): RedirectResponse {
        $action->handle($adjustmentReason);

        return back()->with('success', 'Motivo de ajuste eliminado correctamente.');
    }

    /**
     * Toggle the active status of the specified stock adjustment reason.
     */
    public function toggleAdjustmentReason(
        StockAdjustmentReason $adjustmentReason,
        ToggleStockAdjustmentReasonStatus $action
    ): RedirectResponse {
        $action->handle($adjustmentReason);

        return back()->with('success', 'Estado del motivo de ajuste actualizado.');
    }
}
