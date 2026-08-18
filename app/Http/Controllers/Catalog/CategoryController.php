<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\CreateCategory;
use App\Actions\Catalog\ToggleCategoryStatus;
use App\Actions\Catalog\UpdateCategory;
use App\Data\Catalog\CategoryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Models\Catalog\Category;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()->orderBy('name')->get();

        return Inertia::render('catalog/categories/index', [
            'categories' => CategoryData::collect($categories),
        ]);
    }

    public function store(StoreCategoryRequest $request, CreateCategory $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Categoría creada correctamente.');
    }

    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategory $action): RedirectResponse
    {
        $action->handle($category, $request->validated());

        return back()->with('success', 'Categoría actualizada correctamente.');
    }

    public function toggleStatus(Category $category, ToggleCategoryStatus $action): RedirectResponse
    {
        $action->handle($category);

        return back()->with('success', 'Estado de la categoría actualizado.');
    }
}
