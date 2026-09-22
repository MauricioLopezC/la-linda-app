<?php

namespace App\Http\Requests\Purchasing;

use App\Enums\Catalog\ArticleStatus;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Catalog\Article;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

class StoreSupplierVoucherRequest extends FormRequest
{
    /**
     * Unit of measure stored for concept lines (no catalog article). Those rows transcribe a
     * charge, discount or adjustment that only carries a description and an amount.
     */
    private const CONCEPT_UNIT_OF_MEASURE = '—';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('type');
        $isRemito = $type === SupplierVoucherType::Remito->value;

        $submittedItems = $this->input('items', []);
        $items = is_array($submittedItems)
            ? array_map(function (mixed $item) use ($isRemito): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                $articleId = Arr::get($item, 'article_id') ?: null;
                $poItemId = Arr::get($item, 'purchase_order_item_id') ?: null;
                $lineTotal = $this->normalizeArgentineMoney(Arr::get($item, 'line_total'));
                $unitPrice = $this->normalizeArgentineMoney(Arr::get($item, 'unit_price'));

                if ($isRemito) {
                    $unitPrice = ($unitPrice === null || $unitPrice === '') ? '0.00' : $unitPrice;
                    $lineTotal = ($lineTotal === null || $lineTotal === '') ? '0.00' : $lineTotal;
                }

                // Concept lines (no catalog article) are transcribed with just a description and
                // an amount. Quantity, unit and unit price are not asked for on screen: they are
                // derived here so the stored row keeps the table's "> 0" invariants without the
                // user inventing a fictional "1 unit @ $x".
                if ($articleId === null) {
                    return [
                        'article_id' => null,
                        'purchase_order_item_id' => null,
                        'description' => $this->normalizeRequiredText(Arr::get($item, 'description')),
                        'quantity' => '1',
                        'unit_of_measure' => self::CONCEPT_UNIT_OF_MEASURE,
                        'unit_price' => $lineTotal,
                        'line_total' => $lineTotal,
                    ];
                }

                return [
                    'article_id' => $articleId,
                    'purchase_order_item_id' => $poItemId !== null ? (int) $poItemId : null,
                    'description' => $this->normalizeRequiredText(Arr::get($item, 'description')),
                    'quantity' => $this->normalizeDecimal(Arr::get($item, 'quantity')),
                    'unit_of_measure' => $this->normalizeRequiredText(Arr::get($item, 'unit_of_measure')),
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }, $submittedItems)
            : $submittedItems;

        $totalAmount = $this->normalizeArgentineMoney($this->input('total_amount'));
        if ($isRemito && ($totalAmount === null || $totalAmount === '')) {
            $totalAmount = '0.00';
        }

        $warehouseId = $this->input('warehouse_id');

        $this->merge([
            'warehouse_id' => $warehouseId !== null && $warehouseId !== '' ? (int) $warehouseId : null,
            'point_of_sale' => $this->normalizeFiscalNumber($this->input('point_of_sale'), 4),
            'number' => $this->normalizeFiscalNumber($this->input('number'), 8),
            'total_amount' => $totalAmount,
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
     *     warehouse_id?: ?int, associated_invoice_id: ?int, associated_amount: ?string,
     *     items: array<int, array{article_id: ?int, description: string, quantity: string,
     *         unit_of_measure: string, unit_price: string, line_total: string, purchase_order_item_id?: ?int}>
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
            $poItemId = $item['purchase_order_item_id'] ?? null;
            $items[] = [
                'article_id' => $articleId === null ? null : (int) $articleId,
                'purchase_order_item_id' => $poItemId === null ? null : (int) $poItemId,
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
        $warehouseId = $validated['warehouse_id'] ?? null;

        return [
            'supplier_id' => (int) $validated['supplier_id'],
            'warehouse_id' => $warehouseId === null ? null : (int) $warehouseId,
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

        $isRemito = $this->input('type') === SupplierVoucherType::Remito->value;
        $hasImputations = is_array($this->input('items'))
            && collect($this->input('items'))->contains(fn ($i) => is_array($i) && ! empty($i['purchase_order_item_id']));

        $warehouseRules = ['prohibited'];
        if ($isRemito) {
            $warehouseTable = (new Warehouse)->getTable();
            if (! $hasImputations) {
                $warehouseRules = [
                    'required',
                    'integer',
                    Rule::exists($warehouseTable, 'id')->where(
                        fn (Builder $query): Builder => $query->where('is_active', true)
                    ),
                ];
            } else {
                $warehouseRules = [
                    'nullable',
                    'integer',
                    Rule::exists($warehouseTable, 'id')->where(
                        fn (Builder $query): Builder => $query->where('is_active', true)
                    ),
                ];
            }
        }

        $letterRules = $isRemito
            ? ['required', Rule::in(['R', 'X'])]
            : ['required', Rule::in(['A', 'B', 'C', 'M'])];

        $dueDateRules = $isRemito
            ? ['prohibited']
            : ['nullable', 'date_format:Y-m-d', 'after_or_equal:issue_date'];

        $totalAmountMin = $isRemito ? 'min:0' : 'min:0.01';
        $itemAmountMin = $isRemito ? 'min:0' : 'min:0.01';

        return [
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists($supplierTable, 'id')->where(
                    fn (Builder $query): Builder => $query->where('is_active', true)
                ),
            ],
            'warehouse_id' => $warehouseRules,
            'type' => ['required', Rule::enum(SupplierVoucherType::class)],
            'letter' => $letterRules,
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
            'due_date' => $dueDateRules,
            'total_amount' => ['required', 'numeric', 'decimal:0,2', $totalAmountMin, 'max:9999999999.99'],
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
            'items.*' => ['required', 'array:article_id,purchase_order_item_id,description,quantity,unit_of_measure,unit_price,line_total'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'items.*.article_id' => [
                'nullable',
                'integer',
                Rule::exists($articleTable, 'id')->where(
                    fn (Builder $query): Builder => $query->where('status', ArticleStatus::Active->value)
                ),
            ],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:999999999.999'],
            'items.*.unit_of_measure' => ['required', 'string', 'max:50'],
            'items.*.unit_price' => ['required', 'numeric', 'decimal:0,2', $itemAmountMin, 'max:9999999999.99'],
            'items.*.line_total' => ['required', 'numeric', 'decimal:0,2', $itemAmountMin, 'max:9999999999.99'],
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
            'warehouse_id' => 'depósito',
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
            'items.*.purchase_order_item_id' => 'orden de compra del ítem :position',
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
            'warehouse_id.prohibited' => 'Solo los remitos pueden tener un depósito asignado.',
            'warehouse_id.required' => 'El depósito es obligatorio para un remito libre.',
            'warehouse_id.exists' => 'El depósito seleccionado no existe o no se encuentra activo.',
            'point_of_sale.regex' => 'El punto de venta debe contener hasta 4 dígitos numéricos.',
            'number.regex' => 'El número de comprobante debe contener hasta 8 dígitos numéricos.',
            'number.unique' => 'Ya existe un comprobante del proveedor con el mismo tipo, letra, punto de venta y número.',
            'issue_date.before_or_equal' => 'La fecha de emisión no puede ser posterior a la fecha actual.',
            'due_date.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a la fecha de emisión.',
            'due_date.prohibited' => 'El remito no admite fecha de vencimiento.',
            'letter.in' => 'La letra no es válida para el tipo de comprobante seleccionado.',
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('type');
            $isRemito = $type === SupplierVoucherType::Remito->value;
            $supplierId = (int) $this->input('supplier_id');
            $items = $this->input('items', []);

            if (! is_array($items)) {
                return;
            }

            if ($isRemito) {
                $hasCatalogArticle = collect($items)
                    ->filter(fn ($item) => is_array($item))
                    ->contains(fn ($item) => ! empty($item['article_id']));

                if (! $hasCatalogArticle) {
                    $validator->errors()->add('items', 'El remito debe contener al menos un renglón con artículo de catálogo.');
                }
            }

            $poItemIds = collect($items)
                ->pluck('purchase_order_item_id')
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();

            if (empty($poItemIds)) {
                return;
            }

            $poItems = PurchaseOrderItem::query()
                ->with(['purchaseOrder', 'article'])
                ->whereKey($poItemIds)
                ->get()
                ->keyBy('id');

            if ($isRemito) {
                $warehouseIds = $poItems->map(fn ($item) => $item->purchaseOrder->warehouse_id)->unique()->values();
                if ($warehouseIds->count() > 1) {
                    $validator->errors()->add(
                        'warehouse_id',
                        'Las órdenes de compra seleccionadas pertenecen a depósitos distintos. Debe registrar remitos separados por depósito.'
                    );
                } elseif ($warehouseIds->isNotEmpty()) {
                    $derivedWarehouseId = (int) $warehouseIds->first();
                    $submittedWarehouseId = $this->input('warehouse_id');
                    if ($submittedWarehouseId !== null && (int) $submittedWarehouseId !== $derivedWarehouseId) {
                        $validator->errors()->add(
                            'warehouse_id',
                            'El depósito seleccionado no coincide con el depósito de la orden de compra.'
                        );
                    }
                }
            }

            foreach ($items as $index => $item) {
                $poItemId = Arr::get($item, 'purchase_order_item_id');
                if (! is_numeric($poItemId)) {
                    continue;
                }

                /** @var PurchaseOrderItem|null $poItem */
                $poItem = $poItems->get((int) $poItemId);
                if ($poItem === null) {
                    continue;
                }

                $purchaseOrder = $poItem->purchaseOrder;

                if ($purchaseOrder->supplier_id !== $supplierId) {
                    $validator->errors()->add(
                        "items.{$index}.purchase_order_item_id",
                        "La orden de compra #{$purchaseOrder->order_number} no pertenece al proveedor seleccionado."
                    );
                }

                if (! $purchaseOrder->isIssued()) {
                    $validator->errors()->add(
                        "items.{$index}.purchase_order_item_id",
                        "La orden de compra #{$purchaseOrder->order_number} no se encuentra en estado emitida."
                    );
                }

                // Validación de artículo coincidente
                $articleId = Arr::get($item, 'article_id');
                if ($articleId !== null && (int) $articleId !== $poItem->article_id) {
                    $validator->errors()->add(
                        "items.{$index}.article_id",
                        "El artículo seleccionado no coincide con el artículo solicitado en la orden #{$purchaseOrder->order_number}."
                    );
                }

                // Rechazo preventivo de saldo cero
                if ((float) $poItem->quantityPending() <= 0.0001) {
                    $validator->errors()->add(
                        "items.{$index}.purchase_order_item_id",
                        "El renglón de la orden de compra #{$purchaseOrder->order_number} ya se encuentra cubierto en su totalidad."
                    );
                }
            }
        });
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
