<?php

namespace App\Models\Sales;

use App\Models\Catalog\Article;
use App\Models\Pricing\PriceList;
use Database\Factories\Sales\SaleItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a sale (HU-040). unit_price and price_list_id are a snapshot of the price
 * resolved by HU-056 when the line was added.
 *
 * @property int $id
 * @property int $sale_id
 * @property int $article_id
 * @property string $quantity
 * @property string $unit_price
 * @property int $price_list_id
 * @property string $line_total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Sale $sale
 * @property Article $article
 * @property PriceList $priceList
 */
#[Fillable([
    'sale_id',
    'article_id',
    'quantity',
    'unit_price',
    'price_list_id',
    'line_total',
])]
class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
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

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
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

    /**
     * quantity × unit_price rounded half-up to cents, computed on integers
     * (thousandths × cents) so weighed quantities never drift.
     */
    public static function calculateLineTotal(string $quantity, string $unitPrice): string
    {
        $quantityThousandths = (int) round((float) $quantity * 1000);
        $priceCents = (int) round((float) $unitPrice * 100);

        $lineCents = intdiv($quantityThousandths * $priceCents + 500, 1000);

        return number_format($lineCents / 100, 2, '.', '');
    }
}
