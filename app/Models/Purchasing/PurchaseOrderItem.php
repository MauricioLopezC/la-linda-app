<?php

namespace App\Models\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Models\Catalog\Article;
use Database\Factories\Purchasing\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int $article_id
 * @property string $quantity
 * @property string $unit_price
 * @property string $line_total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property PurchaseOrder $purchaseOrder
 * @property Article $article
 */
#[Fillable([
    'purchase_order_id',
    'article_id',
    'quantity',
    'unit_price',
    'line_total',
])]
class PurchaseOrderItem extends Model
{
    use ConvertsMoneyToCents;

    /** @use HasFactory<PurchaseOrderItemFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
