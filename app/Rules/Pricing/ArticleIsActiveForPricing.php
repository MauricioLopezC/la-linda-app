<?php

namespace App\Rules\Pricing;

use App\Enums\Catalog\ArticleStatus;
use App\Models\Catalog\Article;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Only active articles can be priced (HU-012).
 *
 * An article deactivated after it was priced keeps its row so the stale price can be removed, but
 * it cannot be given a new one: the screen renders it read-only and this rule is what backs that.
 */
class ArticleIsActiveForPricing implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $article = Article::query()->find((int) $value);

        if ($article === null) {
            $fail('El artículo seleccionado no existe.');

            return;
        }

        if ($article->status !== ArticleStatus::Active) {
            $fail("El artículo \"{$article->description}\" está inactivo y no admite precio.");
        }
    }
}
