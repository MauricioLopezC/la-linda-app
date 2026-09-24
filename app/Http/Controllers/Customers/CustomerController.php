<?php

namespace App\Http\Controllers\Customers;

use App\Actions\Customers\CreateCustomer;
use App\Actions\Customers\DeleteCustomer;
use App\Actions\Customers\ToggleCustomerStatus;
use App\Actions\Customers\UpdateCustomer;
use App\Data\Customers\CustomerData;
use App\Data\Pricing\PriceListData;
use App\Enums\Customers\CustomerIdType;
use App\Enums\Customers\CustomerTaxCondition;
use App\Enums\Customers\PersonType;
use App\Enums\Pricing\PriceListScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Models\Customers\Customer;
use App\Models\Pricing\PriceList;
use App\Rules\Customers\ValidCuit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $customers = Customer::query()
            ->with('priceList')
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = trim((string) $request->input('search'));
                $cleanSearch = ValidCuit::sanitize($search);
                $lowerSearch = mb_strtolower($search);

                $query->where(function (Builder $q) use ($lowerSearch, $cleanSearch) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$lowerSearch}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$lowerSearch}%"]);

                    if ($cleanSearch !== '') {
                        $q->orWhere('id_number', 'like', "%{$cleanSearch}%");
                    }
                });
            })
            ->when($request->filled('tax_condition') && $request->input('tax_condition') !== 'all', function (Builder $query) use ($request) {
                $query->where('tax_condition', $request->input('tax_condition'));
            })
            ->when($request->filled('person_type') && $request->input('person_type') !== 'all', function (Builder $query) use ($request) {
                $query->where('person_type', $request->input('person_type'));
            })
            ->when($request->filled('status') && $request->input('status') !== 'all', function (Builder $query) use ($request) {
                match ($request->input('status')) {
                    'active' => $query->where('is_active', true),
                    'inactive' => $query->where('is_active', false),
                    default => $query,
                };
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        /** Listas particulares activas y vigentes disponibles para asignar a clientes (HU-022). */
        $availablePriceLists = PriceList::query()
            ->where('scope', PriceListScope::Particular)
            ->active()
            ->currentlyValid()
            ->orderBy('name')
            ->get();

        return Inertia::render('customers/index', [
            'customers' => CustomerData::collect($customers),
            'taxConditions' => CustomerTaxCondition::toOptions(),
            'personTypes' => PersonType::toOptions(),
            'idTypes' => CustomerIdType::toOptions(),
            'availablePriceLists' => PriceListData::collect($availablePriceLists),
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'tax_condition' => (string) $request->input('tax_condition', 'all'),
                'person_type' => (string) $request->input('person_type', 'all'),
                'status' => (string) $request->input('status', 'all'),
            ],
        ]);
    }

    public function store(StoreCustomerRequest $request, CreateCustomer $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Cliente registrado correctamente.');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, UpdateCustomer $action): RedirectResponse
    {
        $action->handle($customer, $request->validated());

        return back()->with('success', 'Cliente actualizado correctamente.');
    }

    public function toggleStatus(Customer $customer, ToggleCustomerStatus $action): RedirectResponse
    {
        $action->handle($customer);

        return back()->with('success', 'Estado del cliente actualizado.');
    }

    public function destroy(Customer $customer, DeleteCustomer $action): RedirectResponse
    {
        $action->handle($customer);

        return back()->with('success', 'Cliente eliminado correctamente.');
    }
}
