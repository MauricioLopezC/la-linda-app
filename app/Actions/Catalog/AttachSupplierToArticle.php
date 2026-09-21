<?php

namespace App\Actions\Catalog;

use App\Concerns\NormalizesUniqueAttributes;
use App\Enums\Catalog\ArticleStatus;
use App\Models\Catalog\Article;
use App\Models\Catalog\ArticleSupplier;
use App\Models\Purchasing\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttachSupplierToArticle
{
    /**
     * Associate an article to a supplier with the supplier's article code and optional cost.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data): ArticleSupplier
    {
        $article = Article::findOrFail((int) $data['article_id']);
        $supplier = Supplier::findOrFail((int) $data['supplier_id']);

        if ($article->status !== ArticleStatus::Active) {
            throw ValidationException::withMessages([
                'article_id' => 'Solo se pueden asociar proveedores a artículos activos.',
            ]);
        }

        if (! $supplier->is_active) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Solo se pueden asociar artículos a proveedores activos.',
            ]);
        }

        // Uniqueness of (article_id, supplier_id)
        if (ArticleSupplier::query()
            ->where('article_id', $article->id)
            ->where('supplier_id', $supplier->id)
            ->exists()
        ) {
            $duplicateMessage = 'El artículo ya se encuentra asociado a este proveedor.';

            throw ValidationException::withMessages([
                'supplier_id' => $duplicateMessage,
                'article_id' => $duplicateMessage,
            ]);
        }

        // Uniqueness of supplier_article_code within the supplier
        $normalizedCode = NormalizesUniqueAttributes::normalizeUniqueValue($data['supplier_article_code']);

        if (ArticleSupplier::query()
            ->where('supplier_id', $supplier->id)
            ->where('supplier_article_code_normalized', $normalizedCode)
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

        return DB::transaction(function () use ($article, $supplier, $data, $lastCost): ArticleSupplier {
            return ArticleSupplier::create([
                'article_id' => $article->id,
                'supplier_id' => $supplier->id,
                'supplier_article_code' => trim($data['supplier_article_code']),
                'last_cost' => $lastCost,
                'notes' => isset($data['notes']) ? trim((string) $data['notes']) ?: null : null,
            ]);
        });
    }
}
