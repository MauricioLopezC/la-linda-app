<?php

namespace App\Models\Pricing;

use App\Models\Catalog\Article;
use Database\Factories\Pricing\PriceListItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Sale price of one article inside one price list (HU-012).
 *
 * A row only exists for articles that actually have a price in the list: the "articles without a
 * price" filter of the management screen is the absence of a row, not a null price.
 *
 * @property int $id
 * @property int $price_list_id
 * @property int $article_id
 * @property string $price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['price_list_id', 'article_id', 'price'])]
class PriceListItem extends Model
{
    /** @use HasFactory<PriceListItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    /**
     * Get the price list this price belongs to.
     *
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * Get the article this price is set for.
     *
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
