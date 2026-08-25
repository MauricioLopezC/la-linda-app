<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ConsultStockMovements;
use App\Data\Inventory\StockMovementListData;
use App\Data\Inventory\StockMovementTypeData;
use App\Data\Inventory\UserOptionData;
use App\Data\Inventory\WarehouseData;
use App\Http\Controllers\Controller;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementType;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementHistoryController extends Controller
{
    /**
     * Display the stock movement history view.
     */
    public function index(Request $request, ConsultStockMovements $consultAction): Response
    {
        $filters = [
            'search' => $request->query('search'),
            'warehouse_id' => $request->filled('warehouse_id') ? (int) $request->query('warehouse_id') : null,
            'stock_movement_type_id' => $request->filled('stock_movement_type_id') ? (int) $request->query('stock_movement_type_id') : null,
            'user_id' => $request->filled('user_id') ? (int) $request->query('user_id') : null,
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $movements = $consultAction->execute($filters);

        $warehouses = Warehouse::query()->active()->with('branch')->orderBy('name')->get();
        $movementTypes = StockMovementType::query()->active()->orderBy('name')->get();

        $usersWithMovements = User::query()
            ->whereIn('id', StockMovement::query()->select('user_id')->distinct())
            ->orderBy('name')
            ->get();

        return Inertia::render('inventory/movements/index', [
            'movements' => StockMovementListData::collect($movements),
            'warehouses' => WarehouseData::collect($warehouses),
            'movementTypes' => StockMovementTypeData::collect($movementTypes),
            'users' => UserOptionData::collect($usersWithMovements),
            'filters' => $filters,
        ]);
    }
}
