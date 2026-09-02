<?php

namespace App\Models\Purchasing;

use Database\Factories\Purchasing\PaymentOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a payment order: the amount imputed to a single invoice (HU-027).
 *
 * @property int $id
 * @property int $payment_order_id
 * @property int $supplier_voucher_id
 * @property string $amount_applied
 * @property PaymentOrder $paymentOrder
 * @property SupplierVoucher $voucher
 */
#[Fillable(['payment_order_id', 'supplier_voucher_id', 'amount_applied'])]
class PaymentOrderItem extends Model
{
    /** @use HasFactory<PaymentOrderItemFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_applied' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<PaymentOrder, $this> */
    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class);
    }

    /**
     * The invoice this line pays down.
     *
     * @return BelongsTo<SupplierVoucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(SupplierVoucher::class, 'supplier_voucher_id');
    }
}
