<?php

namespace App\Http\Requests\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceWebOrderRequest extends FormRequest
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
            'pickup_branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pickup_branch_id.required' => 'Elegí la sucursal donde vas a retirar el pedido.',
            'pickup_branch_id.integer' => 'La sucursal de retiro no es válida.',
            'pickup_branch_id.exists' => 'La sucursal de retiro elegida no está activa.',
            'notes.max' => 'Las observaciones no pueden superar los 500 caracteres.',
        ];
    }
}
