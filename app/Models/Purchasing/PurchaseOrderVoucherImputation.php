<?php

namespace App\Models\Purchasing;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Imputation linking a supplier voucher item with a purchase order item (HU-037).
 *
 * @property int $id
 * @property int $purchase_order_item_id
 * @property int $supplier_voucher_item_id
 * @property string $quantity_received
 * @property string $quantity_excess
 * @property Carbon $created_at
 * @property PurchaseOrderItem $purchaseOrderItem
 * @property SupplierVoucherItem $supplierVoucherItem
 */
#[Fillable([
    'purchase_order_item_id',
    'supplier_voucher_item_id',
    'quantity_received',
    'quantity_excess',
])]
class PurchaseOrderVoucherImputation extends Model
{
    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_received' => 'decimal:3',
            'quantity_excess' => 'decimal:3',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PurchaseOrderItem, $this> */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /** @return BelongsTo<SupplierVoucherItem, $this> */
    public function supplierVoucherItem(): BelongsTo
    {
        return $this->belongsTo(SupplierVoucherItem::class);
    }
}
