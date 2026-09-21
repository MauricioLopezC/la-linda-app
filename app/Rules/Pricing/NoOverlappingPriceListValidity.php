<?php

namespace App\Rules\Pricing;

use App\Enums\Pricing\PriceListScope;
use App\Models\Pricing\PriceList;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A sales channel can only resolve one price, so at most one active base list may cover a given
 * date per channel. Preferential lists (scope `particular`) are resolved by customer assignment
 * instead, so they are free to overlap and are skipped here.
 */
class NoOverlappingPriceListValidity implements ValidationRule
{
    public function __construct(
        private readonly ?string $scope,
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
        if (! $this->isActive || $this->scope !== PriceListScope::Canal->value) {
            return;
        }

        if (! is_string($this->channel) || ! is_string($value)) {
            return;
        }

        $overlapping = PriceList::query()
            ->forChannel($this->channel)
            ->active()
            ->overlapping($value, $this->validTo)
            ->when($this->ignoreId, fn ($query, $ignoreId) => $query->whereKeyNot($ignoreId))
            ->first();

        if ($overlapping === null) {
            return;
        }

        $fail($overlapping->valid_to === null
            ? "El canal ya tiene la lista \"{$overlapping->name}\" activa sin fecha de fin. Poné primero una fecha de fin a esa lista para que esta tome la posta."
            : "El período se superpone con la lista \"{$overlapping->name}\", activa para el mismo canal.");
    }
}
