<?php

namespace App\Models\Sales;

use App\Models\Catalog\Article;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\VatRate;
use Database\Factories\Sales\SaleItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a sale (HU-040). unit_price and price_list_id are a snapshot of the price
 * resolved by HU-056 when the line was added, and vat_rate_id / vat_rate a snapshot of the
 * article's VAT rate (HU-063). net_amount and vat_amount are derived from line_total and
 * vat_rate on every save, so no action can leave them out of step.
 *
 * @property int $id
 * @property int $sale_id
 * @property int $article_id
 * @property string $quantity
 * @property string $unit_price
 * @property int $price_list_id
 * @property string $line_total
 * @property int $vat_rate_id
 * @property string $vat_rate
 * @property string $net_amount
 * @property string $vat_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Sale $sale
 * @property Article $article
 * @property PriceList $priceList
 * @property VatRate $vatRate
 */
#[Fillable([
    'sale_id',
    'article_id',
    'quantity',
    'unit_price',
    'price_list_id',
    'line_total',
    'vat_rate_id',
    'vat_rate',
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
            'vat_rate' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SaleItem $item): void {
            if (! $item->exists || $item->isDirty(['line_total', 'vat_rate'])) {
                $breakdown = self::calculateVatBreakdown($item->line_total, $item->vat_rate);

                $item->net_amount = $breakdown['net_amount'];
                $item->vat_amount = $breakdown['vat_amount'];
            }
        });
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

    /** @return BelongsTo<VatRate, $this> */
    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
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

    /**
     * Split a VAT-included line total into net and VAT (HU-063): net = total / (1 + rate)
     * rounded half-up to cents, and VAT = total − net, so both always add up to the total.
     * Computed on integers (cents × hundredths of a percent) to avoid float drift.
     *
     * @return array{net_amount: string, vat_amount: string}
     */
    public static function calculateVatBreakdown(string $lineTotal, string $vatRate): array
    {
        $totalCents = (int) round((float) $lineTotal * 100);
        $rateHundredths = (int) round((float) $vatRate * 100);
        $divisor = 10000 + $rateHundredths;

        $netCents = intdiv($totalCents * 10000 * 2 + $divisor, 2 * $divisor);

        return [
            'net_amount' => number_format($netCents / 100, 2, '.', ''),
            'vat_amount' => number_format(($totalCents - $netCents) / 100, 2, '.', ''),
        ];
    }
}
