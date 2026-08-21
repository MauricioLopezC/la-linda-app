<?php

namespace App\Http\Controllers\Pricing;

use App\Actions\Pricing\CreateVatRate;
use App\Actions\Pricing\ToggleVatRateStatus;
use App\Actions\Pricing\UpdateVatRate;
use App\Data\Pricing\VatRateData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\StoreVatRateRequest;
use App\Http\Requests\Pricing\UpdateVatRateRequest;
use App\Models\Pricing\VatRate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VatRateController extends Controller
{
    public function index(): Response
    {
        $vatRates = VatRate::query()->orderBy('description')->get();

        return Inertia::render('pricing/vat-rates/index', [
            'vatRates' => VatRateData::collect($vatRates),
        ]);
    }

    public function store(StoreVatRateRequest $request, CreateVatRate $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Alícuota de IVA creada correctamente.');
    }

    public function update(UpdateVatRateRequest $request, VatRate $vatRate, UpdateVatRate $action): RedirectResponse
    {
        $action->handle($vatRate, $request->validated());

        return back()->with('success', 'Alícuota de IVA actualizada correctamente.');
    }

    public function toggleStatus(VatRate $vatRate, ToggleVatRateStatus $action): RedirectResponse
    {
        $action->handle($vatRate);

        return back()->with('success', 'Estado de la alícuota de IVA actualizado.');
    }
}
