<?php

namespace App\Actions\Catalog;

use App\Concerns\NormalizesUniqueAttributes;
use App\Models\Catalog\ArticleSupplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateArticleSupplier
{
    /**
     * Update an article-supplier association.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(ArticleSupplier $association, array $data): ArticleSupplier
    {
        // Uniqueness of supplier_article_code within the supplier, ignoring current association
        $normalizedCode = NormalizesUniqueAttributes::normalizeUniqueValue($data['supplier_article_code']);

        if (ArticleSupplier::query()
            ->where('supplier_id', $association->supplier_id)
            ->where('supplier_article_code_normalized', $normalizedCode)
            ->whereKeyNot($association->id)
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'supplier_article_code' => 'El código de artículo ya está en uso para este proveedor.',
            ]);
        }

        // Cost validation: must be > 0 when informed
        $lastCost = null;
        if (isset($data['last_cost']) && $data['last_cost'] !== '') {
            $numericCost = (float) $data['last_cost'];
            if ($numericCost <= 0) {
                throw ValidationException::withMessages([
                    'last_cost' => 'El costo debe ser mayor a cero cuando se informa.',
                ]);
            }
            $lastCost = $numericCost;
        }

        return DB::transaction(function () use ($association, $data, $lastCost): ArticleSupplier {
            $association->update([
                'supplier_article_code' => trim($data['supplier_article_code']),
                'last_cost' => $lastCost,
                'notes' => isset($data['notes']) ? trim((string) $data['notes']) ?: null : null,
            ]);

            return $association;
        });
    }
}
