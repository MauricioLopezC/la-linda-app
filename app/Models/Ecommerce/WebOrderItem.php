<?php

namespace App\Models\Ecommerce;

use App\Models\Catalog\Article;
use App\Models\Pricing\PriceList;
use Database\Factories\Ecommerce\WebOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an online order, with the price frozen when the order was placed.
 *
 * @property int $id
 * @property int $web_order_id
 * @property int $article_id
 * @property string $quantity
 * @property string $unit_price
 * @property int $price_list_id
 * @property string $line_total
 * @property WebOrder $webOrder
 * @property Article $article
 * @property PriceList $priceList
 */
#[Fillable(['web_order_id', 'article_id', 'quantity', 'unit_price', 'price_list_id', 'line_total'])]
class WebOrderItem extends Model
{
    /** @use HasFactory<WebOrderItemFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<WebOrder, $this> */
    public function webOrder(): BelongsTo
    {
        return $this->belongsTo(WebOrder::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<PriceList, $this> */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }
}
