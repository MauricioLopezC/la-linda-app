<?php

namespace App\Http\Requests\Purchasing;

use App\Enums\Purchasing\SupplierTaxCondition;
use App\Rules\Purchasing\ValidCuit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
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
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'tax_id' => ['required', 'string', new ValidCuit, 'unique:suppliers,tax_id'],
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
}
