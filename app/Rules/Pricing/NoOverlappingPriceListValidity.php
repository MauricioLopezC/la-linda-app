<?php

namespace App\Rules\Pricing;

use App\Models\Pricing\PriceList;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoOverlappingPriceListValidity implements ValidationRule
{
    public function __construct(
        private readonly ?string $channel,
        private readonly ?string $validTo,
        private readonly bool $isActive,
        private readonly ?int $ignoreId = null,
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->isActive || ! is_string($this->channel) || ! is_string($value)) {
            return;
        }

        $overlaps = PriceList::query()
            ->where('channel', $this->channel)
            ->where('is_active', true)
            ->when($this->ignoreId, fn ($query, $ignoreId) => $query->whereKeyNot($ignoreId))
            ->when($this->validTo !== null, fn ($query) => $query->where('valid_from', '<=', $this->validTo))
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>=', $value))
            ->exists();

        if ($overlaps) {
            $fail('Ya existe una lista activa y vigente para el canal seleccionado en ese período.');
        }
    }
}
