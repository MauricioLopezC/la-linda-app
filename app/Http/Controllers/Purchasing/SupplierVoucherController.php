<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\CreateSupplierVoucher;
use App\Data\Purchasing\SupplierOptionData;
use App\Data\Purchasing\SupplierVoucherListData;
use App\Data\Purchasing\SupplierVoucherOptionData;
use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StoreSupplierVoucherRequest;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Rules\Purchasing\ValidCuit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SupplierVoucherController extends Controller
{
    public function index(): Response
    {
        $vouchers = SupplierVoucher::query()
            ->with('supplier:id,business_name')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('purchasing/vouchers/index', [
            'vouchers' => SupplierVoucherListData::collect($vouchers),
        ]);
    }

    public function create(): Response
    {
        $suppliers = Supplier::query()
            ->active()
            ->select(['id', 'business_name', 'tax_id'])
            ->orderBy('business_name')
            ->get();

        return Inertia::render('purchasing/vouchers/create', [
            'suppliers' => SupplierOptionData::collect($suppliers),
            'voucherTypes' => SupplierVoucherOptionData::collect(SupplierVoucherType::toOptions()),
            'letters' => SupplierVoucherOptionData::collect(SupplierVoucherLetter::toOptions()),
            'today' => today()->toDateString(),
        ]);
    }

    public function store(StoreSupplierVoucherRequest $request, CreateSupplierVoucher $action): RedirectResponse
    {
        /** @var array{
         *     supplier_id: int,
         *     type: string,
         *     letter: string,
         *     point_of_sale: string,
         *     number: string,
         *     issue_date: string,
         *     due_date: ?string,
         *     net_amount: string,
         *     other_taxes_amount: string,
         *     notes: ?string
         * } $data
         */
        $data = $request->validated();
        $action->handle($data);

        return to_route('purchasing.vouchers.index')
            ->with('success', 'Comprobante de proveedor registrado correctamente.');
    }

    public function pdf(SupplierVoucher $supplierVoucher): HttpResponse
    {
        $supplierVoucher->loadMissing('supplier');
        $filename = 'comprobante-'.$supplierVoucher->letter->value.'-'.$supplierVoucher->point_of_sale.'-'.$supplierVoucher->number.'.pdf';

        return Pdf::loadView('pdf.purchasing.supplier-voucher', [
            'voucher' => $supplierVoucher,
            'supplierTaxId' => ValidCuit::format($supplierVoucher->supplier->tax_id),
            'company' => config('company'),
            'stylesheet' => File::get(resource_path('css/pdf/supplier-voucher.css')),
        ])->setPaper('a4')->download($filename);
    }
}
