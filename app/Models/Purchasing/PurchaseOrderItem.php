<?php

namespace App\Models\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Catalog\Article;
use Database\Factories\Purchasing\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

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

    /**
     * Attach the received / invoiced sums as subselects so listing many lines avoids N+1.
     * The quantity methods below detect these attributes on their own.
     *
     * @param  Builder<PurchaseOrderItem>  $query
     */
    public function scopeWithImputedQuantities(Builder $query): void
    {
        $sums = fn (string $column): array => collect([SupplierVoucherType::Remito, SupplierVoucherType::Invoice])
            ->mapWithKeys(fn (SupplierVoucherType $type): array => [
                'imputations as '.self::aggregateAttribute($type, $column) => fn (Builder $imputations): Builder => self::constrainToLiveVouchersOfType($imputations, $type),
            ])
            ->all();

        $query->withSum($sums('quantity_applied'), 'quantity_applied')
            ->withSum($sums('quantity_excess'), 'quantity_excess');
    }

    /** Quantity covered by remitos, capped at the ordered quantity. */
    public function quantityReceived(): string
    {
        return $this->imputedQuantity(SupplierVoucherType::Remito, 'quantity_applied');
    }

    /** Quantity covered by invoices, capped at the ordered quantity. */
    public function quantityInvoiced(): string
    {
        return $this->imputedQuantity(SupplierVoucherType::Invoice, 'quantity_applied');
    }

    /** Quantity received beyond the ordered one (accepted surplus of weighed goods). */
    public function quantityExcessReceived(): string
    {
        return $this->imputedQuantity(SupplierVoucherType::Remito, 'quantity_excess');
    }

    /** Quantity invoiced beyond the ordered one (accepted surplus of weighed goods). */
    public function quantityExcessInvoiced(): string
    {
        return $this->imputedQuantity(SupplierVoucherType::Invoice, 'quantity_excess');
    }

    public function quantityPendingToReceive(): string
    {
        return $this->pendingQuantity($this->quantityReceived());
    }

    public function quantityPendingToInvoice(): string
    {
        return $this->pendingQuantity($this->quantityInvoiced());
    }

    /**
     * Pending quantity of the track a voucher of the given type covers: remitos receive,
     * invoices bill.
     */
    public function quantityPendingFor(SupplierVoucherType $type): string
    {
        return match ($type) {
            SupplierVoucherType::Remito => $this->quantityPendingToReceive(),
            SupplierVoucherType::Invoice => $this->quantityPendingToInvoice(),
            default => throw new LogicException("Los comprobantes de tipo {$type->label()} no se imputan a órdenes de compra."),
        };
    }

    public function isFullyReceived(): bool
    {
        return (float) $this->quantityPendingToReceive() <= 0.0001;
    }

    public function isFullyInvoiced(): bool
    {
        return (float) $this->quantityPendingToInvoice() <= 0.0001;
    }

    /**
     * @param  Builder<PurchaseOrderVoucherImputation>  $imputations
     * @return Builder<PurchaseOrderVoucherImputation>
     */
    private static function constrainToLiveVouchersOfType(Builder $imputations, SupplierVoucherType $type): Builder
    {
        return $imputations->whereHas('supplierVoucherItem.supplierVoucher', fn (Builder $vouchers): Builder => $vouchers
            ->where('type', $type->value)
            ->where('status', '!=', SupplierVoucherStatus::Cancelled->value));
    }

    private static function aggregateAttribute(SupplierVoucherType $type, string $column): string
    {
        $track = $type === SupplierVoucherType::Remito ? 'received' : 'invoiced';

        return "{$track}_{$column}_sum";
    }

    private function imputedQuantity(SupplierVoucherType $type, string $column): string
    {
        $aggregate = self::aggregateAttribute($type, $column);

        if (array_key_exists($aggregate, $this->attributes)) {
            $sum = (float) $this->attributes[$aggregate];
        } elseif ($this->relationLoaded('imputations')) {
            $sum = $this->imputations
                ->filter(function (PurchaseOrderVoucherImputation $imputation) use ($type): bool {
                    $voucher = $imputation->supplierVoucherItem->supplierVoucher;

                    return $voucher->type === $type && $voucher->status !== SupplierVoucherStatus::Cancelled;
                })
                ->sum(fn (PurchaseOrderVoucherImputation $imputation): float => (float) $imputation->{$column});
        } else {
            $sum = (float) self::constrainToLiveVouchersOfType($this->imputations()->getQuery(), $type)->sum($column);
        }

        return number_format($sum, 3, '.', '');
    }

    private function pendingQuantity(string $covered): string
    {
        return number_format(max(0.0, (float) $this->quantity - (float) $covered), 3, '.', '');
    }
}
