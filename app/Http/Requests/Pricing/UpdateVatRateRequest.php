<?php

namespace App\Http\Requests\Pricing;

use App\Models\Pricing\VatRate;
use App\Rules\UniqueNormalizedValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVatRateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $vatRate = $this->route('vat_rate');
        $vatRateId = $vatRate instanceof VatRate ? $vatRate->id : (int) $vatRate;

        return [
            'description' => ['required', 'string', 'min:2', 'max:100', new UniqueNormalizedValue(VatRate::class, 'description_normalized', $vatRateId)],
            'percentage' => ['required', 'numeric', 'between:0,100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['description' => 'descripción', 'percentage' => 'porcentaje', 'is_active' => 'estado'];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('description'))) {
            $this->merge(['description' => trim($this->input('description'))]);
        }
    }
}
