<?php

namespace App\Http\Requests\Sales;

use App\Enums\Sales\CashMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashMovementRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                Rule::in([CashMovementType::Income->value, CashMovementType::Expense->value]),
            ],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'tipo de movimiento',
            'amount' => 'importe',
            'reason' => 'motivo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => 'El importe debe ser mayor a cero.',
            'reason.required' => 'El motivo es obligatorio.',
        ];
    }
}
