<?php

namespace App\Http\Requests\Sales;

use App\Enums\Sales\PaymentMethodKind;
use App\Models\Sales\PaymentMethod;
use App\Rules\UniqueNormalizedValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentMethodRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'min:2', 'max:100', new UniqueNormalizedValue(PaymentMethod::class, 'name_normalized')],
            'kind' => ['sometimes', 'string', Rule::enum(PaymentMethodKind::class)],
            'is_enabled_online' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'kind' => 'clase',
            'is_enabled_online' => 'habilitado en canal online',
            'is_active' => 'estado',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merges = [];
        if (is_string($this->input('name'))) {
            $merges['name'] = trim($this->input('name'));
        }
        if (! $this->has('kind') || $this->input('kind') === null) {
            $merges['kind'] = PaymentMethodKind::Other->value;
        }
        if ($merges !== []) {
            $this->merge($merges);
        }
    }
}
