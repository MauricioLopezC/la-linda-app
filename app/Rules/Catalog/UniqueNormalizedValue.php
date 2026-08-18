<?php

namespace App\Rules\Catalog;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueNormalizedValue implements ValidationRule
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, int|string>  $constraints
     */
    public function __construct(
        private string $modelClass,
        private string $column,
        private ?int $ignoreId = null,
        private array $constraints = [],
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $query = $this->modelClass::query()->where(
            $this->column,
            Str::of($value)->trim()->lower()->toString(),
        );

        foreach ($this->constraints as $column => $constraint) {
            $query->where($column, $constraint);
        }

        if ($this->ignoreId !== null) {
            $query->whereKeyNot($this->ignoreId);
        }

        if ($query->exists()) {
            $fail('El valor ingresado para :attribute ya está en uso.');
        }
    }
}
