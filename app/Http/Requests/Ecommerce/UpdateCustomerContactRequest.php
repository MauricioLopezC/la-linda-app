<?php

namespace App\Http\Requests\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^(?:(?:(?:\+|00)?54\s?9?)?\s?(?:0?[1-9]\d{1,3})?\s?(?:15)?[-\s.]?\d{3,4}[-\s.]?\d{4})$/',
            ],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre y apellido es obligatorio.',
            'phone.regex' => 'El formato del teléfono no es válido. Ej: +54 9 387 1234567',
        ];
    }
}
