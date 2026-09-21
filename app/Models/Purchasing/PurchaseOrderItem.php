<?php

namespace App\Models\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Models\Catalog\Article;
use Database\Factories\Purchasing\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /** @return HasMany<PurchaseOrderVoucherImputation, $this> */
    public function imputations(): HasMany
    {
        return $this->hasMany(PurchaseOrderVoucherImputation::class);
    }

    public function quantityReceived(): string
    {
        if (isset($this->attributes['imputations_received_sum'])) {
            return number_format((float) $this->attributes['imputations_received_sum'], 3, '.', '');
        }

        $sum = $this->imputations()
            ->whereHas('supplierVoucherItem.supplierVoucher', function ($query) {
                $query->where('status', '!=', SupplierVoucherStatus::Cancelled->value);
            })
            ->sum('quantity_received');

        return number_format((float) $sum, 3, '.', '');
    }

    public function quantityExcess(): string
    {
        if (isset($this->attributes['imputations_excess_sum'])) {
            return number_format((float) $this->attributes['imputations_excess_sum'], 3, '.', '');
        }

        $sum = $this->imputations()
            ->whereHas('supplierVoucherItem.supplierVoucher', function ($query) {
                $query->where('status', '!=', SupplierVoucherStatus::Cancelled->value);
            })
            ->sum('quantity_excess');

        return number_format((float) $sum, 3, '.', '');
    }

    public function quantityPending(): string
    {
        $received = (float) $this->quantityReceived();
        $requested = (float) $this->quantity;
        $pending = max(0.0, $requested - $received);

        return number_format($pending, 3, '.', '');
    }

    public function isFullyReceived(): bool
    {
        return (float) $this->quantityPending() <= 0.0001;
    }
}
