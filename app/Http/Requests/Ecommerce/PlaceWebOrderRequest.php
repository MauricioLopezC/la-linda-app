<?php

namespace App\Http\Requests\Ecommerce;

use App\Enums\Ecommerce\DeliveryMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceWebOrderRequest extends FormRequest
{
    /**
     * Prepare inputs for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('delivery_method') || $this->input('delivery_method') === null) {
            $this->merge(['delivery_method' => DeliveryMethod::Pickup->value]);
        }
    }

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
            'delivery_method' => ['required', Rule::enum(DeliveryMethod::class)],
            'pickup_branch_id' => [
                Rule::requiredIf($this->input('delivery_method') === DeliveryMethod::Pickup->value),
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('is_active', true),
            ],
            'shipping_address' => [
                Rule::requiredIf($this->input('delivery_method') === DeliveryMethod::Shipping->value),
                'nullable',
                'string',
                'max:255',
            ],
            'shipping_notes' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'delivery_method.required' => 'Elegí la modalidad de entrega.',
            'delivery_method.enum' => 'La modalidad de entrega no es válida.',
            'pickup_branch_id.required' => 'Elegí la sucursal donde vas a retirar el pedido.',
            'pickup_branch_id.integer' => 'La sucursal de retiro no es válida.',
            'pickup_branch_id.exists' => 'La sucursal de retiro elegida no está activa.',
            'shipping_address.required' => 'Ingresá el domicilio de entrega.',
            'shipping_address.max' => 'El domicilio no puede superar los 255 caracteres.',
            'shipping_notes.max' => 'Las indicaciones de entrega no pueden superar los 255 caracteres.',
            'notes.max' => 'Las observaciones no pueden superar los 500 caracteres.',
        ];
    }
}
