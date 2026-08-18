<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\CreateBrand;
use App\Actions\Catalog\ToggleBrandStatus;
use App\Actions\Catalog\UpdateBrand;
use App\Data\Catalog\BrandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreBrandRequest;
use App\Http\Requests\Catalog\UpdateBrandRequest;
use App\Models\Catalog\Brand;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(): Response
    {
        $brands = Brand::query()->orderBy('name')->get();

        return Inertia::render('catalog/brands/index', [
            'brands' => BrandData::collect($brands),
        ]);
    }

    public function store(StoreBrandRequest $request, CreateBrand $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Marca creada correctamente.');
    }

    public function update(UpdateBrandRequest $request, Brand $brand, UpdateBrand $action): RedirectResponse
    {
        $action->handle($brand, $request->validated());

        return back()->with('success', 'Marca actualizada correctamente.');
    }

    public function toggleStatus(Brand $brand, ToggleBrandStatus $action): RedirectResponse
    {
        $action->handle($brand);

        return back()->with('success', 'Estado de la marca actualizado.');
    }
}
