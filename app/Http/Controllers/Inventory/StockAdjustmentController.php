<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\RegisterStockAdjustment;
use App\Data\Inventory\ArticleStockOptionData;
use App\Data\Inventory\StockAdjustmentReasonData;
use App\Data\Inventory\StockMovementDetailData;
use App\Data\Inventory\WarehouseData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockAdjustmentRequest;
use App\Models\Catalog\Article;
use App\Models\Inventory\StockAdjustmentReason;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockAdjustmentController extends Controller
{
    /**
     * Show the form for creating a new stock adjustment.
     */
    public function create(Request $request): Response
    {
        $warehouses = Warehouse::query()
            ->active()
            ->with('branch')
            ->orderBy('name')
            ->get();

        $reasons = StockAdjustmentReason::query()
            ->active()
            ->orderBy('name')
            ->get();

        return Inertia::render('inventory/adjustments/create', [
            'warehouses' => WarehouseData::collect($warehouses),
            'reasons' => StockAdjustmentReasonData::collect($reasons),
            'initialWarehouseId' => $request->filled('warehouse_id') ? (int) $request->query('warehouse_id') : null,
        ]);
    }

    /**
     * Search articles and fetch their current balance for the given warehouse.
     */
    public function searchArticles(Request $request): JsonResponse
    {
        $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $warehouseId = (int) $request->query('warehouse_id');
        $search = trim((string) $request->query('search', ''));

        $query = Article::query()
            ->active()
            ->with(['category', 'brand', 'unitOfMeasure'])
            ->leftJoin('stock_balances', function ($join) use ($warehouseId) {
                $join->on('articles.id', '=', 'stock_balances.article_id')
                    ->where('stock_balances.warehouse_id', '=', $warehouseId);
            })
            ->select([
                'articles.*',
                'stock_balances.quantity as current_stock_balance',
            ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('articles.description', 'like', "%{$search}%")
                    ->orWhere('articles.internal_code', 'like', "%{$search}%")
                    ->orWhere('articles.barcode', 'like', "%{$search}%");
            });
        }

        $articles = $query->orderBy('articles.description')->limit(20)->get();

        $data = $articles->map(function (Article $article): ArticleStockOptionData {
            $stock = $article->getAttribute('current_stock_balance');
            $currentStock = $stock !== null ? (float) $stock : 0.0;

            return new ArticleStockOptionData(
                id: $article->id,
                description: $article->description,
                internal_code: $article->internal_code,
                barcode: $article->barcode,
                category_name: $article->category->name,
                brand_name: $article->brand?->name,
                unit_of_measure_name: $article->unitOfMeasure->name,
                unit_of_measure_abbreviation: $article->unitOfMeasure->abbreviation,
                current_stock: sprintf('%.3f', $currentStock),
            );
        });

        return response()->json($data);
    }

    /**
     * Store a newly created stock adjustment in storage.
     */
    public function store(StoreStockAdjustmentRequest $request, RegisterStockAdjustment $action): RedirectResponse
    {
        /** @var int $userId */
        $userId = $request->user()->id;

        $data = [
            'warehouse_id' => (int) $request->validated('warehouse_id'),
            'stock_adjustment_reason_id' => (int) $request->validated('stock_adjustment_reason_id'),
            'notes' => $request->validated('notes'),
            'user_id' => $userId,
            'items' => $request->validated('items'),
        ];

        $movement = $action->execute($data);

        return redirect()
            ->route('inventory.adjustments.show', $movement)
            ->with('success', 'Ajuste de stock registrado exitosamente.');
    }

    /**
     * Display the specified stock adjustment receipt (read-only).
     */
    public function show(StockMovement $stockMovement): Response
    {
        $stockMovement->load([
            'warehouse.branch',
            'reason',
            'user',
            'type',
            'items.article.unitOfMeasure',
            'items.article.category',
            'items.article.brand',
        ]);

        return Inertia::render('inventory/adjustments/show', [
            'movement' => StockMovementDetailData::fromModel($stockMovement),
        ]);
    }
}
