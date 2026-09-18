<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\AnnulPaymentOrder;
use App\Actions\Purchasing\IssuePaymentOrder;
use App\Actions\Purchasing\ListPaymentOrders;
use App\Data\Purchasing\PaymentOrderData;
use App\Data\Purchasing\PaymentOrderListData;
use App\Data\Purchasing\SupplierOptionData;
use App\Data\Purchasing\SupplierVoucherListData;
use App\Data\Purchasing\SupplierVoucherOptionData;
use App\Data\Sales\PaymentMethodData;
use App\Enums\Purchasing\PaymentOrderStatus;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\ListPaymentOrdersRequest;
use App\Http\Requests\Purchasing\StorePaymentOrderRequest;
use App\Models\Purchasing\PaymentOrder;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Sales\PaymentMethod;
use App\Rules\Purchasing\ValidCuit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentOrderController extends Controller
{
    /**
     * Display a paginated list of payment orders and egresses with filters.
     */
    public function index(ListPaymentOrdersRequest $request, ListPaymentOrders $action): Response
    {
        $filters = $request->validated();
        $result = $action->handle($filters);

        $suppliers = Supplier::query()
            ->select(['id', 'business_name', 'tax_id'])
            ->orderBy('business_name')
            ->get();

        $paymentMethods = PaymentMethod::query()
            ->orderBy('name')
            ->get();

        return Inertia::render('purchasing/payment-orders/index', [
            'orders' => PaymentOrderListData::collect($result['orders']),
            'suppliers' => SupplierOptionData::collect($suppliers),
            'paymentMethods' => PaymentMethodData::collect($paymentMethods),
            'voucherTypes' => SupplierVoucherOptionData::collect(SupplierVoucherType::toOptions()),
            'statuses' => SupplierVoucherOptionData::collect(
                array_map(
                    fn (PaymentOrderStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                    PaymentOrderStatus::cases()
                )
            ),
            'totalEgresses' => $result['total_egresses'],
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'supplier_id' => isset($filters['supplier_id']) ? (string) $filters['supplier_id'] : '',
                'payment_method_id' => isset($filters['payment_method_id']) ? (string) $filters['payment_method_id'] : '',
                'voucher_type' => (string) ($filters['voucher_type'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'date_from' => (string) ($filters['date_from'] ?? ''),
                'date_to' => (string) ($filters['date_to'] ?? ''),
            ],
        ]);
    }

    /**
     * Export payment orders and vouchers to CSV.
     */
    public function exportCsv(ListPaymentOrdersRequest $request, ListPaymentOrders $action): StreamedResponse
    {
        $filters = $request->validated();
        $query = $action->buildQuery($filters);
        $filename = 'pagos_y_egresos_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'N° Orden',
                'Fecha',
                'Proveedor',
                'Tipo Comprobante',
                'Comprobante Imputado',
                'Importe Imputado',
                'Medios de Pago',
                'Importe Total Orden',
                'Estado',
            ], ';');

            $query->chunk(100, function ($orders) use ($handle) {
                foreach ($orders as $order) {
                    $methodsSummary = $order->paymentMethods
                        ->map(fn ($m) => $m->paymentMethod->name)
                        ->filter()
                        ->unique()
                        ->join(', ') ?: '—';

                    if ($order->items->isEmpty()) {
                        fputcsv($handle, [
                            $order->order_number,
                            $order->date->format('d/m/Y'),
                            $order->supplier->business_name,
                            '—',
                            '—',
                            '0.00',
                            $methodsSummary,
                            (string) $order->total_amount,
                            $order->status->label(),
                        ], ';');
                    } else {
                        foreach ($order->items as $item) {
                            $voucher = $item->voucher;
                            $voucherNumber = $voucher->letter->value.' '.$voucher->point_of_sale.'-'.$voucher->number;

                            fputcsv($handle, [
                                $order->order_number,
                                $order->date->format('d/m/Y'),
                                $order->supplier->business_name,
                                $voucher->type->label(),
                                $voucherNumber,
                                (string) $item->amount_applied,
                                $methodsSummary,
                                (string) $order->total_amount,
                                $order->status->label(),
                            ], ';');
                        }
                    }
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Export payment orders and vouchers to Excel (XLSX).
     */
    public function exportExcel(ListPaymentOrdersRequest $request, ListPaymentOrders $action): BinaryFileResponse
    {
        $filters = $request->validated();
        $query = $action->buildQuery($filters);
        $filename = 'pagos_y_egresos_'.now()->format('Ymd_His').'.xlsx';
        $tempPath = tempnam(sys_get_temp_dir(), 'export_') ?: sys_get_temp_dir().'/export_'.uniqid().'.xlsx';

        $writer = new Writer;
        $writer->openToFile($tempPath);

        $writer->addRow(Row::fromValues([
            'N° Orden',
            'Fecha',
            'Proveedor',
            'Tipo Comprobante',
            'Comprobante Imputado',
            'Importe Imputado',
            'Medios de Pago',
            'Importe Total Orden',
            'Estado',
        ]));

        $query->chunk(100, function ($orders) use ($writer) {
            foreach ($orders as $order) {
                $methodsSummary = $order->paymentMethods
                    ->map(fn ($m) => $m->paymentMethod->name)
                    ->filter()
                    ->unique()
                    ->join(', ') ?: '—';

                if ($order->items->isEmpty()) {
                    $writer->addRow(Row::fromValues([
                        $order->order_number,
                        $order->date->format('d/m/Y'),
                        $order->supplier->business_name,
                        '—',
                        '—',
                        (float) $order->total_amount,
                        $methodsSummary,
                        (float) $order->total_amount,
                        $order->status->label(),
                    ]));
                } else {
                    foreach ($order->items as $item) {
                        $voucher = $item->voucher;
                        $voucherNumber = $voucher->letter->value.' '.$voucher->point_of_sale.'-'.$voucher->number;

                        $writer->addRow(Row::fromValues([
                            $order->order_number,
                            $order->date->format('d/m/Y'),
                            $order->supplier->business_name,
                            $voucher->type->label(),
                            $voucherNumber,
                            (float) $item->amount_applied,
                            $methodsSummary,
                            (float) $order->total_amount,
                            $order->status->label(),
                        ]));
                    }
                }
            }
        });

        $writer->close();

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

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
         *     date: string,
         *     notes: ?string,
         *     payment_methods: array<int, array{payment_method_id: int, amount: string, reference?: string, source_account?: string, transaction_number?: string, check_number?: string, check_due_date?: string}>,
         *     items: array<int, array{supplier_voucher_id: int, amount_applied: string}>
         * } $data
         */
        $data = $request->validated();

        /** @var PaymentOrderData $orderData */
        $orderData = $action->handle($data, auth()->id() !== null ? (int) auth()->id() : null);

        return to_route('purchasing.payment-orders.create')
            ->with('success', "Orden de pago {$orderData->order_number} emitida correctamente.")
            ->with('issuedOrder', $orderData);
    }

    /**
     * Return the invoices with a pending balance for a given supplier.
     *
     * This endpoint feeds the invoice selector in Clara's form. It returns only:
     * - vouchers of type Invoice (not credit/debit notes)
     * - with outstanding_amount > 0 (i.e. not fully paid)
     *
     * The withBalanceAggregates() scope avoids N+1 by loading all four balance components
     * as sub-selects in a single query. PHP-level filter via outstandingAmount() is then applied
     * to exclude fully-settled vouchers — the same pattern used elsewhere in the module.
     *
     * Returns invoices, debit notes, and free credit notes (those with remaining unapplied
     * credit) — all three types can participate in a payment order.
     */
    public function invoices(Supplier $supplier): JsonResponse
    {
        $vouchers = SupplierVoucher::query()
            ->withBalanceAggregates()
            ->with('supplier:id,business_name')
            ->where('supplier_id', $supplier->id)
            ->where('status', '!=', SupplierVoucherStatus::Cancelled->value)
            ->orderByDesc('issue_date')
            ->orderByDesc('supplier_vouchers.id')
            ->get()
            ->filter(fn (SupplierVoucher $v): bool => (float) $v->outstandingAmount() > 0)
            ->values();

        return response()->json(SupplierVoucherListData::collect($vouchers));
    }

    /**
     * Annul a payment order.
     */
    public function destroy(PaymentOrder $order, AnnulPaymentOrder $action): RedirectResponse
    {
        $action->handle($order, auth()->id() !== null ? (int) auth()->id() : null);

        return back()->with('success', "Orden de pago {$order->order_number} anulada correctamente.");
    }

    /**
     * Download the payment order as PDF.
     */
    public function pdf(PaymentOrder $order): \Illuminate\Http\Response
    {
        $order->loadMissing([
            'supplier',
            'paymentMethods.paymentMethod',
            'items.voucher',
            'user',
        ]);

        $cssPath = resource_path('css/pdf/purchase-order.css');
        $stylesheet = File::exists($cssPath) ? File::get($cssPath) : '';

        $pdf = Pdf::loadView('pdf.purchasing.payment-order', [
            'order' => $order,
            'supplierTaxId' => ValidCuit::format($order->supplier->tax_id) ?? $order->supplier->tax_id,
            'company' => config('company'),
            'stylesheet' => $stylesheet,
        ]);

        return $pdf->stream("Orden_de_Pago_{$order->order_number}.pdf");
    }
}
