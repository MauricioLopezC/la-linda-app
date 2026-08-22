<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\CreateArticle;
use App\Actions\Catalog\DiscontinueArticle;
use App\Actions\Catalog\UpdateArticle;
use App\Data\Catalog\ArticleData;
use App\Data\Catalog\BrandData;
use App\Data\Catalog\CategoryData;
use App\Data\Catalog\UnitOfMeasureData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreArticleRequest;
use App\Http\Requests\Catalog\UpdateArticleRequest;
use App\Models\Catalog\Article;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    public function index(): Response
    {
        $articles = Article::query()
            ->with(['category', 'brand', 'unitOfMeasure'])
            ->orderBy('description')
            ->get();

        return Inertia::render('catalog/articles/index', [
            'articles' => ArticleData::collect($articles),
            'categories' => CategoryData::collect(Category::query()->orderBy('name')->get()),
            'brands' => BrandData::collect(Brand::query()->orderBy('name')->get()),
            'unitsOfMeasure' => UnitOfMeasureData::collect(UnitOfMeasure::query()->orderBy('name')->get()),
        ]);
    }

    public function store(StoreArticleRequest $request, CreateArticle $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Artículo creado correctamente.');
    }

    public function update(UpdateArticleRequest $request, Article $article, UpdateArticle $action): RedirectResponse
    {
        $action->handle($article, $request->validated());

        return back()->with('success', 'Artículo actualizado correctamente.');
    }

    public function destroy(Article $article, DiscontinueArticle $action): RedirectResponse
    {
        $action->handle($article);

        return back()->with('success', 'Artículo dado de baja correctamente.');
    }
}
