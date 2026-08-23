<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ConsultStockBalances;
use App\Data\Catalog\CategoryData;
use App\Data\Inventory\StockBalanceData;
use App\Data\Inventory\WarehouseData;
use App\Http\Controllers\Controller;
use App\Models\Catalog\Category;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockConsultationController extends Controller
{
    /**
     * Display the stock balances inquiry view.
     */
    public function index(Request $request, ConsultStockBalances $consultAction): Response
    {
        $filters = [
            'search' => $request->query('search'),
            'category_id' => $request->filled('category_id') ? (int) $request->query('category_id') : null,
            'warehouse_id' => $request->filled('warehouse_id') ? (int) $request->query('warehouse_id') : null,
            'status' => $request->query('status', 'all'),
        ];

        $result = $consultAction->execute($filters);

        $categories = Category::query()->active()->orderBy('name')->get();
        $warehouses = Warehouse::query()->active()->with('branch')->orderBy('name')->get();

        return Inertia::render('inventory/stocks/index', [
            'stocks' => StockBalanceData::collect($result['stocks']),
            'totals' => $result['totals'],
            'categories' => CategoryData::collect($categories),
            'warehouses' => WarehouseData::collect($warehouses),
            'filters' => $filters,
        ]);
    }
}
