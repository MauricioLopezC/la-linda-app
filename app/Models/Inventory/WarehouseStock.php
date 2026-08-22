<?php

namespace App\Models\Inventory;

use App\Models\Catalog\Article;
use Database\Factories\Inventory\WarehouseStockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $article_id
 * @property int $warehouse_id
 * @property float $quantity
 * @property float $min_stock
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['article_id', 'warehouse_id', 'quantity', 'min_stock'])]
class WarehouseStock extends Model
{
    /** @use HasFactory<WarehouseStockFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'min_stock' => 'float',
        ];
    }

    /**
     * Get the article associated with this stock record.
     *
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Get the warehouse where this stock is stored.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Check if the stock is completely depleted.
     */
    public function isOutOfStock(): bool
    {
        return $this->quantity <= 0;
    }

    /**
     * Check if the stock is below or equal to the minimum threshold, but not out of stock.
     */
    public function isBelowMinimum(): bool
    {
        return $this->quantity > 0 && $this->quantity <= $this->min_stock;
    }

    /**
     * Get the stock status classification.
     */
    public function stockStatus(): string
    {
        if ($this->isOutOfStock()) {
            return 'out_of_stock';
        }

        if ($this->isBelowMinimum()) {
            return 'low_stock';
        }

        return 'in_stock';
    }
}
