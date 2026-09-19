<?php

namespace App\Models\Catalog;

use App\Concerns\NormalizesUniqueAttributes;
use App\Models\Purchasing\Supplier;
use Database\Factories\Catalog\ArticleSupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $article_id
 * @property int $supplier_id
 * @property string $supplier_article_code
 * @property string $supplier_article_code_normalized
 * @property string|null $last_cost
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Article $article
 * @property-read Supplier $supplier
 */
#[Fillable([
    'article_id',
    'supplier_id',
    'supplier_article_code',
    'last_cost',
    'notes',
])]
class ArticleSupplier extends Pivot
{
    /** @use HasFactory<ArticleSupplierFactory> */
    use HasFactory;

    use NormalizesUniqueAttributes;

    public $incrementing = true;

    protected $table = 'article_supplier';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_cost' => 'decimal:2',
        ];
    }

    /** @return array<string, string> */
    protected function uniqueAttributesToNormalize(): array
    {
        return [
            'supplier_article_code' => 'supplier_article_code_normalized',
        ];
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
