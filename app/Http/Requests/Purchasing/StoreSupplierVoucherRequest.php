<?php

namespace App\Http\Requests\Purchasing;

use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreSupplierVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'point_of_sale' => $this->normalizeFiscalNumber($this->input('point_of_sale'), 4),
            'number' => $this->normalizeFiscalNumber($this->input('number'), 8),
            'net_amount' => $this->normalizeOptionalMoney($this->input('net_amount')),
            'other_taxes_amount' => $this->normalizeOptionalMoney($this->input('other_taxes_amount')),
            'notes' => $this->normalizeOptionalText($this->input('notes')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $supplierTable = (new Supplier)->getTable();
        $voucherTable = (new SupplierVoucher)->getTable();

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
            'net_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'other_taxes_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'vat_amount' => ['prohibited'],
            'total_amount' => ['prohibited'],
            'status' => ['prohibited'],
            'pending_balance' => ['prohibited'],
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
            'net_amount' => 'importe neto gravado',
            'vat_amount' => 'IVA',
            'other_taxes_amount' => 'otros tributos',
            'total_amount' => 'importe total',
            'notes' => 'observaciones',
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
            '*.decimal' => 'Los importes admiten como máximo dos decimales.',
            'vat_amount.prohibited' => 'El IVA se calcula automáticamente y no puede cargarse manualmente.',
            'total_amount.prohibited' => 'El importe total se calcula automáticamente y no puede cargarse manualmente.',
            'status.prohibited' => 'El estado se calcula automáticamente y no puede cargarse manualmente.',
            'pending_balance.prohibited' => 'El saldo pendiente se calcula automáticamente y no puede cargarse manualmente.',
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

    private function normalizeOptionalMoney(mixed $value): mixed
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return '0.00';
        }

        return $this->normalizeMoney($value);
    }

    private function normalizeMoney(mixed $value): mixed
    {
        if (! is_scalar($value)) {
            return $value;
        }

        return Str::of((string) $value)->trim()->replace(',', '.')->toString();
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
