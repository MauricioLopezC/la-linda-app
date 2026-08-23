<?php

namespace App\Models\Catalog;

use App\Concerns\NormalizesUniqueAttributes;
use App\Enums\Catalog\ArticleStatus;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovementItem;
use Database\Factories\Catalog\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $description
 * @property string $internal_code
 * @property string $internal_code_normalized
 * @property string|null $barcode
 * @property string|null $barcode_normalized
 * @property int $category_id
 * @property int|null $brand_id
 * @property int $unit_of_measure_id
 * @property ArticleStatus $status
 * @property bool $is_online_publishable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['description', 'internal_code', 'barcode', 'category_id', 'brand_id', 'unit_of_measure_id', 'status', 'is_online_publishable'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    use NormalizesUniqueAttributes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => ArticleStatus::Active,
        'is_online_publishable' => false,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'is_online_publishable' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::Active);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<UnitOfMeasure, $this> */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    /**
     * Get the stock this article holds across every warehouse.
     *
     * @return HasMany<StockBalance, $this>
     */
    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    /**
     * Get the movement lines this article appears in.
     *
     * @return HasMany<StockMovementItem, $this>
     */
    public function stockMovementItems(): HasMany
    {
        return $this->hasMany(StockMovementItem::class);
    }

    public function hasStockMovements(): bool
    {
        return $this->stockMovementItems()->exists();
    }

    public function hasSales(): bool
    {
        // Future relation with Sale once invoicing is implemented.
        return false;
    }

    /** @return array<string, string> */
    protected function uniqueAttributesToNormalize(): array
    {
        return [
            'internal_code' => 'internal_code_normalized',
            'barcode' => 'barcode_normalized',
        ];
    }
}
