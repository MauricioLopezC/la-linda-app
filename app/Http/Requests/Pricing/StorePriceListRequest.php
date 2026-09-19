<?php

namespace App\Http\Requests\Pricing;

use App\Enums\Pricing\PriceListChannel;
use App\Models\Pricing\PriceList;
use App\Rules\Pricing\NoOverlappingPriceListValidity;
use App\Rules\UniqueNormalizedValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriceListRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:100', new UniqueNormalizedValue(PriceList::class, 'name_normalized')],
            'description' => ['nullable', 'string'],
            'channel' => ['required', Rule::enum(PriceListChannel::class)],
            'valid_from' => [
                'required',
                'date',
                new NoOverlappingPriceListValidity(
                    channel: $this->filled('channel') ? $this->string('channel')->toString() : null,
                    validTo: $this->filled('valid_to') ? $this->string('valid_to')->toString() : null,
                    isActive: $this->boolean('is_active', true),
                ),
            ],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
            'channel' => 'canal',
            'valid_from' => 'vigencia desde',
            'valid_to' => 'vigencia hasta',
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
