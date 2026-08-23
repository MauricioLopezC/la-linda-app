<?php

namespace App\Models\Inventory;

use App\Models\Catalog\Article;
use Database\Factories\Inventory\StockMovementItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Detail line of a stock movement.
 *
 * @property int $id
 * @property int $stock_movement_id
 * @property int $article_id
 * @property string $quantity
 * @property string|null $system_quantity
 */
#[Fillable(['stock_movement_id', 'article_id', 'quantity', 'system_quantity'])]
class StockMovementItem extends Model
{
    /** @use HasFactory<StockMovementItemFactory> */
    use HasFactory;

    /**
     * The date of a line is the date of its header.
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'system_quantity' => 'decimal:3',
        ];
    }

    /**
     * Get the movement this line belongs to.
     *
     * @return BelongsTo<StockMovement, $this>
     */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    /**
     * Get the article moved by this line.
     *
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
