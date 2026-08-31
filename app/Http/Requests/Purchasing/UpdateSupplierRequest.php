<?php

namespace App\Http\Requests\Purchasing;

use App\Enums\Purchasing\SupplierTaxCondition;
use App\Models\Purchasing\Supplier;
use App\Rules\Purchasing\ValidCuit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tax_id')) {
            $this->merge([
                'tax_id' => ValidCuit::sanitize((string) $this->tax_id),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Supplier|null $supplier */
        $supplier = $this->route('supplier');

        return [
            'business_name' => ['required', 'string', 'max:255'],
            'tax_id' => [
                'required',
                'string',
                new ValidCuit,
                Rule::unique('suppliers', 'tax_id')->ignore($supplier?->id),
            ],
            'tax_condition' => ['required', Rule::enum(SupplierTaxCondition::class)],
            'address' => ['nullable', 'string', 'max:255'],
            'rubro' => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:255'],
            'commercial_terms' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_name.required' => 'La razón social es obligatoria.',
            'tax_id.required' => 'El CUIT es obligatorio.',
            'tax_id.unique' => 'Ya existe un proveedor registrado con ese CUIT.',
            'tax_condition.required' => 'La condición fiscal es obligatoria.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Supplier|null $supplier */
            $supplier = $this->route('supplier');

            if ($supplier instanceof Supplier && $supplier->hasAssociatedRecords()) {
                if ($this->input('tax_id') !== $supplier->tax_id) {
                    $validator->errors()->add('tax_id', 'No se puede modificar el CUIT de un proveedor que ya posee comprobantes u órdenes registradas.');
                }
            }
        });
    }
}
