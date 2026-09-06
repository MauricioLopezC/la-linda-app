<?php

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class CancelPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'El motivo de cancelación es obligatorio.',
            'reason.min' => 'El motivo de cancelación debe tener al menos 3 caracteres.',
            'reason.max' => 'El motivo de cancelación no puede superar los 1000 caracteres.',
        ];
    }
}
