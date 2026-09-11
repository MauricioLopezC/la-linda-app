<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\AnnulSupplierVoucher;
use App\Actions\Purchasing\CreateSupplierVoucher;
use App\Data\Purchasing\PurchaseOrderArticleOptionData;
use App\Data\Purchasing\SupplierOptionData;
use App\Data\Purchasing\SupplierVoucherData;
use App\Data\Purchasing\SupplierVoucherListData;
use App\Data\Purchasing\SupplierVoucherOptionData;
use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\AnnulSupplierVoucherRequest;
use App\Http\Requests\Purchasing\ListSupplierVouchersRequest;
use App\Http\Requests\Purchasing\SearchSupplierVoucherArticlesRequest;
use App\Http\Requests\Purchasing\StoreSupplierVoucherRequest;
use App\Models\Catalog\Article;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SupplierVoucherController extends Controller
{
    public function index(ListSupplierVouchersRequest $request): Response
    {
        $filters = $request->validated();
        $search = trim((string) ($filters['search'] ?? ''));

        $vouchers = SupplierVoucher::query()
            ->withBalanceAggregates()
            ->with('supplier:id,business_name')
            ->when($search !== '', fn (Builder $query): Builder => $this->applySearch($query, $search))
            ->when(isset($filters['supplier_id']), fn (Builder $query): Builder => $query->where('supplier_id', $filters['supplier_id']))
            ->when(isset($filters['type']), fn (Builder $query): Builder => $query->where('type', $filters['type']))
            ->when(isset($filters['status']), fn (Builder $query): Builder => $query->where('status', $filters['status']))
            ->when(isset($filters['date_from']), fn (Builder $query): Builder => $query->whereDate('issue_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query): Builder => $query->whereDate('issue_date', '<=', $filters['date_to']))
            ->when((bool) ($filters['only_overdue'] ?? false), fn (Builder $query): Builder => $query
                ->whereDate('due_date', '<', today())
                ->whereIn('status', [
                    SupplierVoucherStatus::Pending->value,
                    SupplierVoucherStatus::PartiallyPaid->value,
                ]))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('purchasing/vouchers/index', [
            'vouchers' => SupplierVoucherListData::collect($vouchers),
            'suppliers' => SupplierOptionData::collect(
                Supplier::query()->select(['id', 'business_name', 'tax_id'])->orderBy('business_name')->get()
            ),
            'voucherTypes' => SupplierVoucherOptionData::collect(SupplierVoucherType::toOptions()),
            'statuses' => SupplierVoucherOptionData::collect(
                array_map(
                    fn (SupplierVoucherStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                    SupplierVoucherStatus::cases()
                )
            ),
            'filters' => [
                'search' => $search,
                'supplier_id' => isset($filters['supplier_id']) ? (string) $filters['supplier_id'] : '',
                'type' => (string) ($filters['type'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'date_from' => (string) ($filters['date_from'] ?? ''),
                'date_to' => (string) ($filters['date_to'] ?? ''),
                'only_overdue' => (bool) ($filters['only_overdue'] ?? false),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('purchasing/vouchers/create', [
            'suppliers' => SupplierOptionData::collect(
                Supplier::query()->active()->select(['id', 'business_name', 'tax_id'])->orderBy('business_name')->get()
            ),
            'voucherTypes' => SupplierVoucherOptionData::collect(SupplierVoucherType::toOptions()),
            'letters' => SupplierVoucherOptionData::collect(SupplierVoucherLetter::toOptions()),
            'today' => today()->toDateString(),
        ]);
    }

    public function searchArticles(SearchSupplierVoucherArticlesRequest $request): JsonResponse
    {
        $search = Article::normalizeUniqueValue((string) $request->validated('search'));

        $articles = Article::query()
            ->active()
            ->with('unitOfMeasure:id,name,abbreviation')
            ->select(['id', 'internal_code', 'description', 'barcode', 'unit_of_measure_id'])
            ->where(function (Builder $query) use ($search): void {
                $query->whereRaw('LOWER(description) LIKE ?', ["%{$search}%"])
                    ->orWhere('internal_code_normalized', 'like', "%{$search}%")
                    ->orWhere('barcode_normalized', 'like', "%{$search}%");
            })
            ->orderByRaw(
                'case when internal_code_normalized = ? or barcode_normalized = ? then 0 else 1 end',
                [$search, $search]
            )
            ->orderBy('description')
            ->limit(20)
            ->get()
            ->map(fn (Article $article): array => PurchaseOrderArticleOptionData::fromModel($article)->toArray());

        return response()->json($articles);
    }

    public function store(StoreSupplierVoucherRequest $request, CreateSupplierVoucher $action): RedirectResponse
    {
        $voucher = $action->handle($request->voucherData());

        return to_route('purchasing.vouchers.show', $voucher)
            ->with('success', 'Comprobante de proveedor registrado correctamente.');
    }

    public function show(SupplierVoucher $supplierVoucher): Response
    {
        $supplierVoucher->load(['supplier', 'items.article', 'annulledByUser']);

        return Inertia::render('purchasing/vouchers/show', [
            'voucher' => SupplierVoucherData::fromModel($supplierVoucher),
        ]);
    }

    public function annul(
        AnnulSupplierVoucherRequest $request,
        SupplierVoucher $supplierVoucher,
        AnnulSupplierVoucher $action,
    ): RedirectResponse {
        $data = $request->validated();
        $action->handle($supplierVoucher, (string) $data['reason']);

        return back()->with('success', 'Comprobante anulado correctamente.');
    }

    /**
     * @param  Builder<SupplierVoucher>  $query
     * @return Builder<SupplierVoucher>
     */
    private function applySearch(Builder $query, string $search): Builder
    {
        $lowerSearch = mb_strtolower($search);
        $digits = preg_replace('/\D/', '', $search) ?? '';

        return $query->where(function (Builder $nested) use ($lowerSearch, $digits) {
            $nested->whereHas('supplier', fn (Builder $supplierQuery): Builder => $supplierQuery
                ->whereRaw('LOWER(business_name) LIKE ?', ["%{$lowerSearch}%"]));

            if ($digits !== '') {
                $nested->orWhere('number', 'like', "%{$digits}%")
                    ->orWhere('point_of_sale', 'like', "%{$digits}%");
            }

            if (strlen($digits) > 8) {
                $nested->orWhere(function (Builder $identityQuery) use ($digits) {
                    $identityQuery
                        ->where('point_of_sale', substr($digits, -12, 4))
                        ->where('number', substr($digits, -8));
                });
            }
        });
    }
}
