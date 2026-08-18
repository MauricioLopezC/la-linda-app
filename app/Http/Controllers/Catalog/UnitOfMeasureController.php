<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\CreateUnitOfMeasure;
use App\Actions\Catalog\ToggleUnitOfMeasureStatus;
use App\Actions\Catalog\UpdateUnitOfMeasure;
use App\Data\Catalog\UnitOfMeasureData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreUnitOfMeasureRequest;
use App\Http\Requests\Catalog\UpdateUnitOfMeasureRequest;
use App\Models\Catalog\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UnitOfMeasureController extends Controller
{
    public function index(): Response
    {
        $unitsOfMeasure = UnitOfMeasure::query()->orderBy('name')->get();

        return Inertia::render('catalog/units-of-measure/index', [
            'unitsOfMeasure' => UnitOfMeasureData::collect($unitsOfMeasure),
        ]);
    }

    public function store(StoreUnitOfMeasureRequest $request, CreateUnitOfMeasure $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Unidad de medida creada correctamente.');
    }

    public function update(UpdateUnitOfMeasureRequest $request, UnitOfMeasure $unitOfMeasure, UpdateUnitOfMeasure $action): RedirectResponse
    {
        $action->handle($unitOfMeasure, $request->validated());

        return back()->with('success', 'Unidad de medida actualizada correctamente.');
    }

    public function toggleStatus(UnitOfMeasure $unitOfMeasure, ToggleUnitOfMeasureStatus $action): RedirectResponse
    {
        $action->handle($unitOfMeasure);

        return back()->with('success', 'Estado de la unidad de medida actualizado.');
    }
}
