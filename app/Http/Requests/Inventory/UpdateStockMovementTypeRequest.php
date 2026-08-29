<?php

namespace App\Http\Requests\Inventory;

use App\Models\Inventory\StockMovementType;
use App\Rules\UniqueNormalizedValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockMovementTypeRequest extends FormRequest
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
        /** @var StockMovementType|int|string|null $movementType */
        $movementType = $this->route('movement_type');
        $movementTypeId = $movementType instanceof StockMovementType ? $movementType->id : (int) $movementType;

        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:100',
                new UniqueNormalizedValue(StockMovementType::class, 'name_normalized', $movementTypeId),
            ],
            'sign' => ['required', 'integer', Rule::in([1, -1])],
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
            'sign' => 'signo de afectación',
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
