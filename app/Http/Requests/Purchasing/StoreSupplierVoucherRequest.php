<?php

namespace App\Http\Requests\Purchasing;

use App\Enums\Catalog\ArticleStatus;
use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Catalog\Article;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use LogicException;

class StoreSupplierVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $submittedItems = $this->input('items', []);
        $items = is_array($submittedItems)
            ? array_map(function (mixed $item): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                return [
                    'article_id' => Arr::get($item, 'article_id') ?: null,
                    'description' => $this->normalizeRequiredText(Arr::get($item, 'description')),
                    'quantity' => $this->normalizeDecimal(Arr::get($item, 'quantity')),
                    'unit_of_measure' => $this->normalizeRequiredText(Arr::get($item, 'unit_of_measure')),
                    'unit_price' => $this->normalizeArgentineMoney(Arr::get($item, 'unit_price')),
                    'line_total' => $this->normalizeArgentineMoney(Arr::get($item, 'line_total')),
                ];
            }, $submittedItems)
            : $submittedItems;

        $this->merge([
            'point_of_sale' => $this->normalizeFiscalNumber($this->input('point_of_sale'), 4),
            'number' => $this->normalizeFiscalNumber($this->input('number'), 8),
            'total_amount' => $this->normalizeArgentineMoney($this->input('total_amount')),
            'notes' => $this->normalizeOptionalText($this->input('notes')),
            'associated_invoice_id' => $this->input('associated_invoice_id') ?: null,
            'associated_amount' => $this->filled('associated_amount')
                ? $this->normalizeArgentineMoney($this->input('associated_amount'))
                : null,
            'items' => $items,
        ]);
    }

    /**
     * @return array{
     *     supplier_id: int, type: string, letter: string, point_of_sale: string, number: string,
     *     issue_date: string, due_date: ?string, total_amount: string, notes: ?string,
     *     associated_invoice_id: ?int, associated_amount: ?string,
     *     items: array<int, array{article_id: ?int, description: string, quantity: string,
     *         unit_of_measure: string, unit_price: string, line_total: string}>
     * }
     */
    public function voucherData(): array
    {
        $validated = $this->validated();
        $validatedItems = $validated['items'] ?? [];

        if (! is_array($validatedItems)) {
            throw new LogicException('Los ítems validados deben ser un arreglo.');
        }

        $items = [];

        foreach ($validatedItems as $item) {
            if (! is_array($item)) {
                throw new LogicException('Cada ítem validado debe ser un arreglo.');
            }

            $articleId = $item['article_id'] ?? null;
            $items[] = [
                'article_id' => $articleId === null ? null : (int) $articleId,
                'description' => (string) $item['description'],
                'quantity' => (string) $item['quantity'],
                'unit_of_measure' => (string) $item['unit_of_measure'],
                'unit_price' => (string) $item['unit_price'],
                'line_total' => (string) $item['line_total'],
            ];
        }

        $dueDate = $validated['due_date'] ?? null;
        $notes = $validated['notes'] ?? null;
        $associatedInvoiceId = $validated['associated_invoice_id'] ?? null;
        $associatedAmount = $validated['associated_amount'] ?? null;

        return [
            'supplier_id' => (int) $validated['supplier_id'],
            'type' => (string) $validated['type'],
            'letter' => (string) $validated['letter'],
            'point_of_sale' => (string) $validated['point_of_sale'],
            'number' => (string) $validated['number'],
            'issue_date' => (string) $validated['issue_date'],
            'due_date' => $dueDate === null ? null : (string) $dueDate,
            'total_amount' => (string) $validated['total_amount'],
            'notes' => $notes === null ? null : (string) $notes,
            'associated_invoice_id' => $associatedInvoiceId === null ? null : (int) $associatedInvoiceId,
            'associated_amount' => $associatedAmount === null ? null : (string) $associatedAmount,
            'items' => $items,
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $supplierTable = (new Supplier)->getTable();
        $voucherTable = (new SupplierVoucher)->getTable();
        $articleTable = (new Article)->getTable();

        return [
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists($supplierTable, 'id')->where(
                    fn (Builder $query): Builder => $query->where('is_active', true)
                ),
            ],
            'type' => ['required', Rule::enum(SupplierVoucherType::class)],
            'letter' => ['required', Rule::enum(SupplierVoucherLetter::class)],
            'point_of_sale' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'number' => [
                'required',
                'string',
                'size:8',
                'regex:/^\d{8}$/',
                Rule::unique($voucherTable, 'number')->where(
                    fn (Builder $query): Builder => $query
                        ->where('supplier_id', $this->input('supplier_id'))
                        ->where('type', $this->input('type'))
                        ->where('letter', $this->input('letter'))
                        ->where('point_of_sale', $this->input('point_of_sale'))
                ),
            ],
            'issue_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:issue_date'],
            'total_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // HU-054 "vinculación en la carga": only a credit note may reference a source invoice,
            // of the same active supplier. The amount also has to fit the invoice's live pending
            // balance, which needs a row lock, so that check lives in AssociateCreditNoteToInvoice.
            'associated_invoice_id' => [
                'prohibited_unless:type,'.SupplierVoucherType::CreditNote->value,
                'nullable',
                'integer',
                Rule::exists($voucherTable, 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('supplier_id', $this->input('supplier_id'))
                        ->where('type', SupplierVoucherType::Invoice->value)
                        ->where('status', '!=', SupplierVoucherStatus::Cancelled->value)
                ),
            ],
            'associated_amount' => [
                'prohibited_unless:type,'.SupplierVoucherType::CreditNote->value,
                'nullable',
                'required_with:associated_invoice_id',
                'numeric',
                'decimal:0,2',
                'min:0.01',
                'lte:total_amount',
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'array:article_id,description,quantity,unit_of_measure,unit_price,line_total'],
            'items.*.article_id' => [
                'nullable',
                'integer',
                Rule::exists($articleTable, 'id')->where(
                    fn (Builder $query): Builder => $query->where('status', ArticleStatus::Active->value)
                ),
            ],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999.99'],
            'items.*.unit_of_measure' => ['required', 'string', 'max:50'],
            'items.*.unit_price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'items.*.line_total' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'net_amount' => ['prohibited'],
            'vat_amount' => ['prohibited'],
            'other_taxes_amount' => ['prohibited'],
            'status' => ['prohibited'],
            'outstanding_amount' => ['prohibited'],
            'annulled_at' => ['prohibited'],
            'annulled_by' => ['prohibited'],
            'annulment_reason' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'supplier_id' => 'proveedor',
            'type' => 'tipo de comprobante',
            'letter' => 'letra',
            'point_of_sale' => 'punto de venta',
            'number' => 'número de comprobante',
            'issue_date' => 'fecha de emisión',
            'due_date' => 'fecha de vencimiento',
            'total_amount' => 'importe total',
            'notes' => 'observaciones',
            'associated_invoice_id' => 'factura asociada',
            'associated_amount' => 'importe aplicado',
            'items' => 'ítems',
            'items.*.article_id' => 'artículo del ítem :position',
            'items.*.description' => 'descripción del ítem :position',
            'items.*.quantity' => 'cantidad del ítem :position',
            'items.*.unit_of_measure' => 'unidad del ítem :position',
            'items.*.unit_price' => 'precio unitario del ítem :position',
            'items.*.line_total' => 'importe del ítem :position',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'supplier_id.exists' => 'El proveedor seleccionado no existe o está inactivo.',
            'point_of_sale.regex' => 'El punto de venta debe contener hasta 4 dígitos numéricos.',
            'number.regex' => 'El número de comprobante debe contener hasta 8 dígitos numéricos.',
            'number.unique' => 'Ya existe un comprobante del proveedor con el mismo tipo, letra, punto de venta y número.',
            'issue_date.before_or_equal' => 'La fecha de emisión no puede ser posterior a la fecha actual.',
            'due_date.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a la fecha de emisión.',
            'items.required' => 'El comprobante debe contener al menos un ítem.',
            'items.min' => 'El comprobante debe contener al menos un ítem.',
            'items.*.article_id.exists' => 'El artículo del ítem :position no existe o está inactivo.',
            'associated_invoice_id.prohibited_unless' => 'Solo una nota de crédito puede asociarse a una factura.',
            'associated_amount.prohibited_unless' => 'Solo una nota de crédito puede aplicar un importe a una factura.',
            'associated_invoice_id.exists' => 'La factura seleccionada no existe, está anulada o no pertenece al proveedor.',
            'associated_amount.required_with' => 'Indicá el importe de la nota de crédito que se aplica a la factura.',
            'associated_amount.lte' => 'El importe aplicado no puede superar el importe total de la nota de crédito.',
            '*.prohibited' => 'Este dato es derivado y no puede cargarse manualmente.',
        ];
    }

    private function normalizeFiscalNumber(mixed $value, int $length): mixed
    {
        if (! is_scalar($value)) {
            return $value;
        }

        $number = Str::of((string) $value)->trim()->toString();

        if ($number === '' || ! ctype_digit($number) || Str::length($number) > $length) {
            return $number;
        }

        return Str::padLeft($number, $length, '0');
    }

    private function normalizeDecimal(mixed $value): mixed
    {
        if (! is_scalar($value)) {
            return $value;
        }

        return Str::of((string) $value)->trim()->replace(',', '.')->toString();
    }

    private function normalizeArgentineMoney(mixed $value): mixed
    {
        if (! is_scalar($value)) {
            return $value;
        }

        $amount = Str::of((string) $value)->trim();

        return $amount->contains(',')
            ? $amount->replace('.', '')->replace(',', '.')->toString()
            : $amount->toString();
    }

    private function normalizeRequiredText(mixed $value): mixed
    {
        return is_scalar($value) ? Str::of((string) $value)->trim()->toString() : $value;
    }

    private function normalizeOptionalText(mixed $value): mixed
    {
        if (! is_scalar($value)) {
            return $value;
        }

        $text = Str::of((string) $value)->trim()->toString();

        return $text === '' ? null : $text;
    }
}
