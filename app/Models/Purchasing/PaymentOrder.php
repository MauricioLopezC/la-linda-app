<?php

namespace App\Models\Purchasing;

use App\Enums\Purchasing\PaymentOrderStatus;
use App\Models\Sales\PaymentMethod;
use App\Models\User;
use Database\Factories\Purchasing\PaymentOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Payment order header (HU-027).
 *
 * A confirmed order is immutable: there is no update or delete route and the table has no
 * updated_at column. Corrections are counter-entries.
 *
 * @property int $id
 * @property int $supplier_id
 * @property int $payment_method_id
 * @property string $order_number
 * @property Carbon $date
 * @property string $total_amount
 * @property PaymentOrderStatus $status
 * @property string|null $notes
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Supplier $supplier
 * @property PaymentMethod $paymentMethod
 * @property Collection<int, PaymentOrderItem> $items
 * @property User $user
 */
#[Fillable([
    'supplier_id',
    'payment_method_id',
    'order_number',
    'date',
    'total_amount',
    'status',
    'notes',
    'user_id',
])]
class PaymentOrder extends Model
{
    /** @use HasFactory<PaymentOrderFactory> */
    use HasFactory;

    /**
     * The table has no updated_at column, so Eloquent must not try to write it.
     */
    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total_amount' => 'decimal:2',
            'status' => PaymentOrderStatus::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /** @return HasMany<PaymentOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PaymentOrderItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
