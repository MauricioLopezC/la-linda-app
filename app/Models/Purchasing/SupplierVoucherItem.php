<?php

namespace App\Models\Purchasing;

use App\Models\Catalog\Article;
use Database\Factories\Purchasing\SupplierVoucherItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'supplier_voucher_id',
    'position',
    'article_id',
    'description',
    'quantity',
    'unit_of_measure',
    'unit_price',
    'line_total',
])]
class SupplierVoucherItem extends Model
{
    /** @use HasFactory<SupplierVoucherItemFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<SupplierVoucher, $this> */
    public function supplierVoucher(): BelongsTo
    {
        return $this->belongsTo(SupplierVoucher::class, 'supplier_voucher_id');
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
