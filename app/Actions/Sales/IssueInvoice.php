<?php

namespace App\Actions\Sales;

use App\Enums\Customers\CustomerIdType;
use App\Enums\Customers\CustomerTaxCondition;
use App\Enums\Sales\InvoiceType;
use App\Enums\Sales\SaleStatus;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueInvoice
{
    /**
     * Issue an invoice for a confirmed sale (HU-042).
     *
     * Locks the point of sale to ensure strictly correlative numbering without gaps or
     * duplicates, freezes customer fiscal data, amounts and item details into invoices and
     * invoice_items.
     *
     * @throws ValidationException
     */
    public function handle(Sale $sale, User $user): Invoice
    {
        return DB::transaction(function () use ($sale, $user): Invoice {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($lockedSale->status !== SaleStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'sale' => 'Solo se puede emitir factura sobre una venta confirmada.',
                ]);
            }

            if ($lockedSale->invoice()->exists()) {
                throw ValidationException::withMessages([
                    'sale' => 'La venta ya cuenta con una factura emitida.',
                ]);
            }

            if (! $lockedSale->items()->exists()) {
                throw ValidationException::withMessages([
                    'sale' => 'No se puede emitir factura para una venta sin artículos.',
                ]);
            }

            /** @var PointOfSale $pointOfSale */
            $pointOfSale = PointOfSale::query()->whereKey($lockedSale->point_of_sale_id)->lockForUpdate()->firstOrFail();

            $type = $lockedSale->invoiceType();

            $lockedSale->loadMissing('customer');
            $customer = $lockedSale->customer;

            if ($type === InvoiceType::A) {
                if (
                    $customer->tax_condition !== CustomerTaxCondition::ResponsibleInscripto ||
                    $customer->id_type !== CustomerIdType::Cuit ||
                    blank($customer->id_number)
                ) {
                    throw ValidationException::withMessages([
                        'invoice' => 'La Factura A exige que el cliente sea responsable inscripto y tenga CUIT cargado.',
                    ]);
                }
            }

            // The point of sale lock above already serializes numbering; Postgres rejects FOR UPDATE on an aggregate.
            $lastNumber = (int) Invoice::query()
                ->where('point_of_sale_id', $pointOfSale->id)
                ->where('type', $type)
                ->max('number');

            $nextNumber = $lastNumber + 1;

            $lockedSale->loadMissing(['items.article']);

            $invoice = Invoice::create([
                'sale_id' => $lockedSale->id,
                'cash_session_id' => $lockedSale->cash_session_id,
                'point_of_sale_id' => $pointOfSale->id,
                'point_of_sale_number' => $pointOfSale->number,
                'type' => $type,
                'number' => $nextNumber,
                'issued_at' => now(),
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_tax_condition' => $customer->tax_condition->value,
                'customer_id_type' => $customer->id_type->value,
                'customer_id_number' => $customer->id_number,
                'customer_address' => $customer->address,
                'net_amount' => $lockedSale->netAmount(),
                'vat_amount' => $lockedSale->vatAmount(),
                'total_amount' => $lockedSale->total_amount,
                'user_id' => $user->id,
                'created_at' => now(),
            ]);

            /** @var SaleItem $item */
            foreach ($lockedSale->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'article_id' => $item->article_id,
                    'description' => $item->article->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'vat_rate' => $item->vat_rate,
                    'net_amount' => $item->net_amount,
                    'vat_amount' => $item->vat_amount,
                    'line_total' => $item->line_total,
                ]);
            }

            return $invoice->load(['pointOfSale', 'customer', 'user', 'items']);
        });
    }
}
