<?php

namespace App\Models\Purchasing;

use App\Models\Sales\PaymentMethod;
use Database\Factories\Purchasing\PaymentOrderMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payment_order_id
 * @property int $payment_method_id
 * @property string $amount
 * @property string|null $reference
 * @property string|null $source_account
 * @property string|null $transaction_number
 * @property string|null $check_number
 * @property Carbon|null $check_due_date
 * @property PaymentOrder $paymentOrder
 * @property PaymentMethod $paymentMethod
 */
#[Fillable([
    'payment_order_id',
    'payment_method_id',
    'amount',
    'reference',
    'source_account',
    'transaction_number',
    'check_number',
    'check_due_date',
])]
class PaymentOrderMethod extends Model
{
    /** @use HasFactory<PaymentOrderMethodFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'check_due_date' => 'date',
        ];
    }

    /** @return BelongsTo<PaymentOrder, $this> */
    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class);
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
