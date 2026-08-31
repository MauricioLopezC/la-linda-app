<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\CreateSupplier;
use App\Actions\Purchasing\DeleteSupplier;
use App\Actions\Purchasing\ToggleSupplierStatus;
use App\Actions\Purchasing\UpdateSupplier;
use App\Data\Purchasing\SupplierData;
use App\Enums\Purchasing\SupplierTaxCondition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StoreSupplierRequest;
use App\Http\Requests\Purchasing\UpdateSupplierRequest;
use App\Models\Purchasing\Supplier;
use App\Rules\Purchasing\ValidCuit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $suppliers = Supplier::query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = trim((string) $request->input('search'));
                $cleanSearch = ValidCuit::sanitize($search);
                $lowerSearch = mb_strtolower($search);

                $query->where(function (Builder $q) use ($lowerSearch, $cleanSearch) {
                    $q->whereRaw('LOWER(business_name) LIKE ?', ["%{$lowerSearch}%"])
                        ->orWhereRaw('LOWER(rubro) LIKE ?', ["%{$lowerSearch}%"]);

                    if ($cleanSearch !== '') {
                        $q->orWhere('tax_id', 'like', "%{$cleanSearch}%");
                    }
                });
            })
            ->when($request->filled('tax_condition') && $request->input('tax_condition') !== 'all', function (Builder $query) use ($request) {
                $query->where('tax_condition', $request->input('tax_condition'));
            })
            ->when($request->filled('status') && $request->input('status') !== 'all', function (Builder $query) use ($request) {
                match ($request->input('status')) {
                    'active' => $query->where('is_active', true),
                    'inactive' => $query->where('is_active', false),
                    default => $query,
                };
            })
            ->orderBy('business_name')
            ->get();

        return Inertia::render('purchasing/suppliers/index', [
            'suppliers' => SupplierData::collect($suppliers),
            'taxConditions' => SupplierTaxCondition::toOptions(),
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'tax_condition' => (string) $request->input('tax_condition', 'all'),
                'status' => (string) $request->input('status', 'all'),
            ],
        ]);
    }

    public function store(StoreSupplierRequest $request, CreateSupplier $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Proveedor creado correctamente.');
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier, UpdateSupplier $action): RedirectResponse
    {
        $action->handle($supplier, $request->validated());

        return back()->with('success', 'Proveedor actualizado correctamente.');
    }

    public function toggleStatus(Supplier $supplier, ToggleSupplierStatus $action): RedirectResponse
    {
        $action->handle($supplier);

        return back()->with('success', 'Estado del proveedor actualizado.');
    }

    public function destroy(Supplier $supplier, DeleteSupplier $action): RedirectResponse
    {
        $action->handle($supplier);

        return back()->with('success', 'Proveedor eliminado correctamente.');
    }
}
