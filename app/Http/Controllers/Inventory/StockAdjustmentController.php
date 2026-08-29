<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\RegisterStockAdjustment;
use App\Data\Inventory\ArticleStockOptionData;
use App\Data\Inventory\StockMovementDetailData;
use App\Data\Inventory\StockMovementTypeData;
use App\Data\Inventory\WarehouseData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockAdjustmentRequest;
use App\Models\Catalog\Article;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementType;
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

        $movementTypes = StockMovementType::query()
            ->active()
            ->whereNotIn('code', StockMovementType::AUTOMATIC_CODES)
            ->orderBy('name')
            ->get();

        return Inertia::render('inventory/adjustments/create', [
            'warehouses' => WarehouseData::collect($warehouses),
            'movementTypes' => StockMovementTypeData::collect($movementTypes),
            'initialWarehouseId' => $request->filled('warehouse_id') ? (int) $request->query('warehouse_id') : null,
        ]);
    }

    /**
     * Search active articles to add to a manual stock movement.
     */
    public function searchArticles(Request $request): JsonResponse
    {
        $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) $request->query('search', ''));

        $query = Article::query()
            ->active()
            ->with(['category', 'brand', 'unitOfMeasure']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('internal_code', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        $articles = $query->orderBy('description')->limit(20)->get();

        $data = $articles->map(fn (Article $article): ArticleStockOptionData => new ArticleStockOptionData(
            id: $article->id,
            description: $article->description,
            internal_code: $article->internal_code,
            barcode: $article->barcode,
            category_name: $article->category->name,
            brand_name: $article->brand?->name,
            unit_of_measure_name: $article->unitOfMeasure->name,
            unit_of_measure_abbreviation: $article->unitOfMeasure->abbreviation,
        ));

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
            'stock_movement_type_id' => (int) $request->validated('stock_movement_type_id'),
            'notes' => $request->validated('notes'),
            'user_id' => $userId,
            'items' => $request->validated('items'),
        ];

        $movement = $action->execute($data);

        return redirect()
            ->route('inventory.adjustments.show', $movement)
            ->with('success', 'Movimiento de stock registrado exitosamente.');
    }

    /**
     * Display the specified stock adjustment receipt (read-only).
     */
    public function show(StockMovement $stockMovement): Response
    {
        $stockMovement->load([
            'warehouse.branch',
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
