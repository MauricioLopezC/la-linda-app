<?php

namespace App\Concerns;

use App\Enums\Pricing\PriceListScope;

/**
 * Shared input shaping and field labels for the price list Form Requests.
 */
trait PreparesPriceListInput
{
    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
            'scope' => 'tipo de lista',
            'channel' => 'canal',
            'valid_from' => 'vigencia desde',
            'valid_to' => 'vigencia hasta',
            'is_active' => 'estado',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'channel.required' => 'Una lista de canal necesita indicar a qué canal corresponde.',
        ];
    }

    protected function isChannelScoped(): bool
    {
        return $this->input('scope') === PriceListScope::Canal->value;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }

        // A preferential list is resolved by customer assignment, never by channel.
        if ($this->input('scope') === PriceListScope::Particular->value) {
            $this->merge(['channel' => null]);
        }
    }
}
