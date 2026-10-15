<?php

namespace App\Actions\Sales;

use App\Data\Sales\SalePaymentData;
use App\Enums\Customers\CustomerIdType;
use App\Enums\Customers\CustomerTaxCondition;
use App\Enums\Sales\CashMovementType;
use App\Enums\Sales\SaleChannel;
use App\Models\Sales\CashMovement;
use App\Models\Sales\Invoice;
use App\Rules\Customers\ValidCuit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\File;

class GenerateInvoicePdf
{
    /**
     * Generate an invoice PDF response (stream or download) for counter or online sales (HU-043 / EPIC-15).
     */
    public function handle(Invoice $invoice, bool $download = false): HttpResponse
    {
        $invoice->loadMissing([
            'items' => fn ($query) => $query->orderBy('id'),
            'sale.cashMovements.paymentMethod',
            'sale.webOrder',
            'user',
        ]);

        if ($invoice->sale->channel === SaleChannel::Online) {
            $mpId = $invoice->sale->webOrder?->mp_payment_id;
            $methodName = $mpId !== null ? "Mercado Pago (Op. #{$mpId})" : 'Mercado Pago';

            $payments = [
                new SalePaymentData(
                    id: 0,
                    payment_method_id: 0,
                    payment_method_name: $methodName,
                    payment_method_kind: 'electronico',
                    payment_method_kind_label: 'Mercado Pago',
                    amount: $invoice->total_amount,
                    tendered_amount: null,
                    change_amount: null,
                    created_at: $invoice->issued_at->toIso8601String(),
                    created_at_formatted: $invoice->issued_at->format('d/m/Y H:i'),
                ),
            ];
        } else {
            $payments = $invoice->sale->cashMovements
                ->where('type', CashMovementType::Sale)
                ->sortBy('id')
                ->map(fn (CashMovement $movement): SalePaymentData => SalePaymentData::fromModel($movement))
                ->values()
                ->all();
        }

        $customerIdType = CustomerIdType::tryFrom((string) $invoice->customer_id_type);
        $customerDocument = match (true) {
            $customerIdType === null, $customerIdType === CustomerIdType::SinIdentificar, blank($invoice->customer_id_number) => null,
            $customerIdType === CustomerIdType::Cuit => 'CUIT '.(ValidCuit::format($invoice->customer_id_number) ?? $invoice->customer_id_number),
            default => "{$customerIdType->label()} {$invoice->customer_id_number}",
        };

        $cssPath = resource_path('css/pdf/invoice.css');

        $pdf = Pdf::loadView('pdf.sales.invoice', [
            'invoice' => $invoice,
            'issuer' => config('invoicing.issuer'),
            'customerTaxCondition' => CustomerTaxCondition::tryFrom($invoice->customer_tax_condition)?->label() ?? $invoice->customer_tax_condition,
            'customerDocument' => $customerDocument,
            'vatBreakdown' => $invoice->vatBreakdown(),
            'payments' => $payments,
            'stylesheet' => File::exists($cssPath) ? File::get($cssPath) : '',
        ]);

        $fileName = "Factura_{$invoice->type->value}_{$invoice->formattedNumber()}.pdf";

        return $download ? $pdf->download($fileName) : $pdf->stream($fileName);
    }
}
