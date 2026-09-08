<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\IssuePaymentOrder;
use App\Data\Purchasing\PaymentOrderData;
use App\Data\Purchasing\SupplierOptionData;
use App\Data\Purchasing\SupplierVoucherListData;
use App\Data\Sales\PaymentMethodData;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StorePaymentOrderRequest;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Sales\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentOrderController extends Controller
{
    /**
     * Render the payment order creation form.
     *
     * Passes the minimal props the frontend needs:
     * - suppliers: active suppliers as options
     * - paymentMethods: active payment methods
     * - today: current date string for the date field default
     */
    public function create(): Response
    {
        $suppliers = Supplier::query()
            ->active()
            ->select(['id', 'business_name', 'tax_id'])
            ->orderBy('business_name')
            ->get();

        $paymentMethods = PaymentMethod::query()
            ->active()
            ->orderBy('name')
            ->get();

        return Inertia::render('purchasing/payment-orders/create', [
            'suppliers' => SupplierOptionData::collect($suppliers),
            'paymentMethods' => PaymentMethodData::collect($paymentMethods),
            'today' => today()->toDateString(),
        ]);
    }

    /**
     * Issue the payment order.
     *
     * All business logic lives in IssuePaymentOrder. The controller only wires HTTP
     * in/out: validates via Form Request, delegates to Action, redirects on success.
     */
    public function store(StorePaymentOrderRequest $request, IssuePaymentOrder $action): RedirectResponse
    {
        /** @var array{
         *     supplier_id: int,
         *     payment_method_id: int,
         *     date: string,
         *     notes: ?string,
         *     items: array<int, array{supplier_voucher_id: int, amount_applied: string}>
         * } $data
         */
        $data = $request->validated();

        /** @var PaymentOrderData $orderData */
        $orderData = $action->handle($data, auth()->id() !== null ? (int) auth()->id() : null);

        return to_route('purchasing.payment-orders.create')
            ->with('success', "Orden de pago {$orderData->order_number} emitida correctamente.");
    }

    /**
     * Return the invoices with a pending balance for a given supplier.
     *
     * This endpoint feeds the invoice selector in Clara's form. It returns only:
     * - vouchers of type Invoice (not credit/debit notes)
     * - with outstanding_amount > 0 (i.e. not fully paid)
     *
     * The withBalanceAggregates() scope avoids N+1 by loading all four balance components
     * as sub-selects in a single query. PHP-level filter via pendingBalance() is then applied
     * to exclude fully-settled invoices — the same pattern used elsewhere in the module.
     */
    public function invoices(Supplier $supplier): JsonResponse
    {
        $vouchers = SupplierVoucher::query()
            ->withBalanceAggregates()
            ->with('supplier:id,business_name')
            ->where('supplier_id', $supplier->id)
            ->where('type', SupplierVoucherType::Invoice->value)
            ->orderByDesc('issue_date')
            ->orderByDesc('supplier_vouchers.id')
            ->get()
            ->filter(fn (SupplierVoucher $v): bool => (float) $v->pendingBalance() > 0)
            ->values();

        return response()->json(SupplierVoucherListData::collect($vouchers));
    }
}
