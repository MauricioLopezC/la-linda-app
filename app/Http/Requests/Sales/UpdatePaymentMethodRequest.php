<?php

namespace App\Http\Requests\Sales;

use App\Models\Sales\PaymentMethod;
use App\Rules\UniqueNormalizedValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMethodRequest extends FormRequest
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
        $paymentMethod = $this->route('payment_method');
        $paymentMethodId = $paymentMethod instanceof PaymentMethod ? $paymentMethod->id : (int) $paymentMethod;

        return [
            'name' => ['required', 'string', 'min:2', 'max:100', new UniqueNormalizedValue(PaymentMethod::class, 'name_normalized', $paymentMethodId)],
            'is_enabled_online' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'is_enabled_online' => 'habilitado en canal online', 'is_active' => 'estado'];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }
}
