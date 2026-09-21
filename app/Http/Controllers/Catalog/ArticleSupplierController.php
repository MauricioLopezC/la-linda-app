<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\AttachSupplierToArticle;
use App\Actions\Catalog\DetachSupplierFromArticle;
use App\Actions\Catalog\UpdateArticleSupplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\AttachArticleToSupplierRequest;
use App\Http\Requests\Catalog\AttachSupplierToArticleRequest;
use App\Http\Requests\Catalog\UpdateArticleSupplierRequest;
use App\Models\Catalog\Article;
use App\Models\Catalog\ArticleSupplier;
use App\Models\Purchasing\Supplier;
use Illuminate\Http\RedirectResponse;

class ArticleSupplierController extends Controller
{
    public function storeForArticle(
        AttachSupplierToArticleRequest $request,
        Article $article,
        AttachSupplierToArticle $action,
    ): RedirectResponse {
        $data = array_merge($request->validated(), ['article_id' => $article->id]);
        $action->handle($data);

        return back()->with('success', 'Proveedor asociado correctamente al artículo.');
    }

    public function updateForArticle(
        UpdateArticleSupplierRequest $request,
        Article $article,
        Supplier $supplier,
        UpdateArticleSupplier $action,
    ): RedirectResponse {
        $association = ArticleSupplier::query()
            ->where('article_id', $article->id)
            ->where('supplier_id', $supplier->id)
            ->firstOrFail();

        $action->handle($association, $request->validated());

        return back()->with('success', 'Asociación actualizada correctamente.');
    }

    public function destroyForArticle(
        Article $article,
        Supplier $supplier,
        DetachSupplierFromArticle $action,
    ): RedirectResponse {
        $association = ArticleSupplier::query()
            ->where('article_id', $article->id)
            ->where('supplier_id', $supplier->id)
            ->firstOrFail();

        $action->handle($association);

        return back()->with('success', 'Proveedor desasociado correctamente del artículo.');
    }

    public function storeForSupplier(
        AttachArticleToSupplierRequest $request,
        Supplier $supplier,
        AttachSupplierToArticle $action,
    ): RedirectResponse {
        $data = array_merge($request->validated(), ['supplier_id' => $supplier->id]);
        $action->handle($data);

        return back()->with('success', 'Artículo asociado correctamente al proveedor.');
    }

    public function updateForSupplier(
        UpdateArticleSupplierRequest $request,
        Supplier $supplier,
        Article $article,
        UpdateArticleSupplier $action,
    ): RedirectResponse {
        $association = ArticleSupplier::query()
            ->where('article_id', $article->id)
            ->where('supplier_id', $supplier->id)
            ->firstOrFail();

        $action->handle($association, $request->validated());

        return back()->with('success', 'Asociación actualizada correctamente.');
    }

    public function destroyForSupplier(
        Supplier $supplier,
        Article $article,
        DetachSupplierFromArticle $action,
    ): RedirectResponse {
        $association = ArticleSupplier::query()
            ->where('article_id', $article->id)
            ->where('supplier_id', $supplier->id)
            ->firstOrFail();

        $action->handle($association);

        return back()->with('success', 'Artículo desasociado correctamente del proveedor.');
    }
}
