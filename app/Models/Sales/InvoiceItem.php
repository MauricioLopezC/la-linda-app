<?php

namespace App\Models\Sales;

use App\Models\Catalog\Article;
use Database\Factories\Sales\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Frozen copy of a sale line on its invoice (HU-042).
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $article_id
 * @property string $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $vat_rate
 * @property string $net_amount
 * @property string $vat_amount
 * @property string $line_total
 * @property Invoice $invoice
 * @property Article $article
 */
#[Fillable([
    'invoice_id',
    'article_id',
    'description',
    'quantity',
    'unit_price',
    'vat_rate',
    'net_amount',
    'vat_amount',
    'line_total',
])]
class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
