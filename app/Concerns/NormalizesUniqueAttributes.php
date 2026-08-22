<?php

namespace App\Concerns;

use Illuminate\Support\Str;

trait NormalizesUniqueAttributes
{
    public static function normalizeUniqueValue(string $value): string
    {
        return Str::of($value)->trim()->lower()->toString();
    }

    public static function bootNormalizesUniqueAttributes(): void
    {
        static::saving(function (self $model): void {
            foreach ($model->uniqueAttributesToNormalize() as $attribute => $normalizedAttribute) {
                $value = $model->getAttribute($attribute);

                if ($value === null) {
                    $model->setAttribute($normalizedAttribute, null);

                    continue;
                }

                if (! is_string($value)) {
                    continue;
                }

                $trimmedValue = Str::of($value)->trim()->toString();

                $model->setAttribute($attribute, $trimmedValue);
                $model->setAttribute($normalizedAttribute, static::normalizeUniqueValue($trimmedValue));
            }

            foreach ($model->uniqueScopeValues() as $attribute => $value) {
                $model->setAttribute($attribute, $value);
            }
        });
    }

    /** @return array<string, string> */
    abstract protected function uniqueAttributesToNormalize(): array;

    /** @return array<string, int|string> */
    protected function uniqueScopeValues(): array
    {
        return [];
    }
}
