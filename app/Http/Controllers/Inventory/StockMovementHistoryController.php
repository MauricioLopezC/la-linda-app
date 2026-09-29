<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ConsultArticleKardex;
use App\Actions\Inventory\ConsultStockMovements;
use App\Data\Inventory\ArticleStockOptionData;
use App\Data\Inventory\KardexEntryData;
use App\Data\Inventory\StockMovementListData;
use App\Data\Inventory\StockMovementTypeData;
use App\Data\Inventory\UserOptionData;
use App\Data\Inventory\WarehouseData;
use App\Http\Controllers\Controller;
use App\Models\Catalog\Article;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementType;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementHistoryController extends Controller
{
    /**
     * Display the stock movement history view. With both an article and a warehouse selected it
     * switches to the kardex of that article in that warehouse, with its running balance.
     */
    public function index(Request $request, ConsultStockMovements $consultAction, ConsultArticleKardex $kardexAction): Response
    {
        $filters = [
            'search' => $request->query('search'),
            'article_id' => $request->filled('article_id') ? (int) $request->query('article_id') : null,
            'warehouse_id' => $request->filled('warehouse_id') ? (int) $request->query('warehouse_id') : null,
            'stock_movement_type_id' => $request->filled('stock_movement_type_id') ? (int) $request->query('stock_movement_type_id') : null,
            'user_id' => $request->filled('user_id') ? (int) $request->query('user_id') : null,
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $article = $filters['article_id'] !== null
            ? Article::query()->with(['category', 'brand', 'unitOfMeasure'])->find($filters['article_id'])
            : null;

        $isKardex = $article !== null && $filters['warehouse_id'] !== null;

        $warehouses = Warehouse::query()->active()->with('branch')->orderBy('name')->get();
        $movementTypes = StockMovementType::query()->active()->orderBy('name')->get();

        $usersWithMovements = User::query()
            ->whereIn('id', StockMovement::query()->select('user_id')->distinct())
            ->orderBy('name')
            ->get();

        return Inertia::render('inventory/movements/index', [
            'movements' => $isKardex ? null : StockMovementListData::collect($consultAction->execute($filters)),
            'kardex' => $isKardex ? KardexEntryData::collect($kardexAction->execute($article->id, (int) $filters['warehouse_id'], $filters)) : null,
            'currentBalance' => $isKardex ? sprintf('%.3f', (float) StockBalance::query()
                ->where('article_id', $article->id)
                ->where('warehouse_id', $filters['warehouse_id'])
                ->value('quantity')) : null,
            'article' => $article === null ? null : ArticleStockOptionData::fromModel($article),
            'warehouses' => WarehouseData::collect($warehouses),
            'movementTypes' => StockMovementTypeData::collect($movementTypes),
            'users' => UserOptionData::collect($usersWithMovements),
            'filters' => $filters,
        ]);
    }

    /**
     * Search articles to filter the history by. Inactive articles are included: they keep their
     * movement history after being deactivated.
     */
    public function searchArticles(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $lowerSearch = mb_strtolower(trim((string) $request->query('search')));

        $articles = Article::query()
            ->with(['category', 'brand', 'unitOfMeasure'])
            ->where(function ($query) use ($lowerSearch) {
                $query->whereRaw('LOWER(description) LIKE ?', ["%{$lowerSearch}%"])
                    ->orWhereRaw('LOWER(internal_code) LIKE ?', ["%{$lowerSearch}%"])
                    ->orWhereRaw('LOWER(barcode) LIKE ?', ["%{$lowerSearch}%"]);
            })
            ->orderBy('description')
            ->limit(20)
            ->get();

        return response()->json($articles->map(fn (Article $article): ArticleStockOptionData => ArticleStockOptionData::fromModel($article)));
    }
}
