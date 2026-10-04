<?php

namespace App\Models\Sales;

use App\Enums\Sales\CashMovementType;
use App\Models\User;
use Database\Factories\Sales\CashMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A movement of money in a cash session. The amount is always positive and the sign comes
 * from the type. Immutable: there is no updated_at, and a mistake is corrected with the
 * opposite movement.
 *
 * @property int $id
 * @property int $cash_session_id
 * @property CashMovementType $type
 * @property int $payment_method_id
 * @property string $amount
 * @property int|null $sale_id
 * @property string|null $tendered_amount
 * @property string|null $reason
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property CashSession $cashSession
 * @property PaymentMethod $paymentMethod
 * @property Sale|null $sale
 * @property User $user
 */
#[Fillable([
    'cash_session_id',
    'type',
    'payment_method_id',
    'amount',
    'sale_id',
    'tendered_amount',
    'reason',
    'user_id',
])]
class CashMovement extends Model
{
    /** @use HasFactory<CashMovementFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => CashMovementType::class,
            'amount' => 'decimal:2',
            'tendered_amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CashSession, $this> */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate change (vuelto) if tendered amount exceeds amount.
     */
    public function changeAmount(): float
    {
        if ($this->tendered_amount === null) {
            return 0.0;
        }

        $change = (float) $this->tendered_amount - (float) $this->amount;

        return max(0.0, round($change, 2));
    }
}
