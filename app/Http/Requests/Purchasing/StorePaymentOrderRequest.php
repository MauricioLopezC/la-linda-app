<?php

namespace App\Http\Requests\Purchasing;

use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Sales\PaymentMethod;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePaymentOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => $this->normalizeOptionalText($this->input('notes')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $supplierTable = (new Supplier)->getTable();
        $paymentMethodTable = (new PaymentMethod)->getTable();
        $voucherTable = (new SupplierVoucher)->getTable();

        return [
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists($supplierTable, 'id')->where(
                    fn (Builder $query): Builder => $query->where('is_active', true)
                ),
            ],
            'payment_method_id' => [
                'required',
                'integer',
                Rule::exists($paymentMethodTable, 'id')->where(
                    fn (Builder $query): Builder => $query->where('is_active', true)
                ),
            ],
            'date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.supplier_voucher_id' => [
                'required',
                'integer',
                Rule::exists($voucherTable, 'id'),
                'distinct',
            ],
            'items.*.amount_applied' => [
                'required',
                'numeric',
                'decimal:0,2',
                'min:0.01',
                'max:9999999999.99',
            ],
            // Calculated by the server; must not be sent by the client.
            'total_amount' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'supplier_id' => 'proveedor',
            'payment_method_id' => 'medio de pago',
            'date' => 'fecha de la orden',
            'notes' => 'observaciones',
            'items' => 'facturas a pagar',
            'items.*.supplier_voucher_id' => 'factura',
            'items.*.amount_applied' => 'importe imputado',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'supplier_id.exists' => 'El proveedor seleccionado no existe o está inactivo.',
            'payment_method_id.exists' => 'El medio de pago seleccionado no existe o está inactivo.',
            'items.min' => 'Debe incluir al menos una factura para emitir la orden.',
            'items.*.supplier_voucher_id.exists' => 'El comprobante seleccionado no existe.',
            'items.*.supplier_voucher_id.distinct' => 'No puede imputar la misma factura más de una vez en la misma orden.',
            'items.*.amount_applied.decimal' => 'El importe imputado admite como máximo dos decimales.',
            'total_amount.prohibited' => 'El importe total se calcula automáticamente y no puede enviarse manualmente.',
        ];
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
