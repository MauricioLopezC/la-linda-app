<?php

namespace App\Http\Requests\Inventory;

use App\Models\Inventory\StockAdjustmentReason;
use App\Rules\UniqueNormalizedValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStockAdjustmentReasonRequest extends FormRequest
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
        /** @var StockAdjustmentReason|int|string|null $reason */
        $reason = $this->route('adjustment_reason');
        $reasonId = $reason instanceof StockAdjustmentReason ? $reason->id : (int) $reason;

        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:100',
                new UniqueNormalizedValue(StockAdjustmentReason::class, 'name_normalized', $reasonId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
            'is_active' => 'estado',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }
}
