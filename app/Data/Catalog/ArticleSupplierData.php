<?php

namespace App\Data\Catalog;

use App\Models\Catalog\ArticleSupplier;
use Spatie\LaravelData\Data;

class ArticleSupplierData extends Data
{
    public function __construct(
        public int $id,
        public int $article_id,
        public string $article_description,
        public string $article_internal_code,
        public ?string $article_barcode,
        public int $supplier_id,
        public string $supplier_business_name,
        public string $supplier_tax_id,
        public string $supplier_article_code,
        public ?string $last_cost,
        public ?string $notes,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function fromModel(ArticleSupplier $record): self
    {
        return new self(
            id: $record->id,
            article_id: $record->article_id,
            article_description: $record->article->description,
            article_internal_code: $record->article->internal_code,
            article_barcode: $record->article->barcode,
            supplier_id: $record->supplier_id,
            supplier_business_name: $record->supplier->business_name,
            supplier_tax_id: $record->supplier->tax_id,
            supplier_article_code: $record->supplier_article_code,
            last_cost: $record->last_cost !== null ? number_format((float) $record->last_cost, 2, '.', '') : null,
            notes: $record->notes,
            created_at: $record->created_at?->toIso8601String(),
            updated_at: $record->updated_at?->toIso8601String(),
        );
    }
}
