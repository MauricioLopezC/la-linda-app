<?php

namespace App\Models\Sales;

use Database\Factories\Sales\CashSessionClosureLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Expected vs. declared amount of one payment method when a session closes (HU-060).
 *
 * @property int $id
 * @property int $cash_session_id
 * @property int $payment_method_id
 * @property string $expected_amount
 * @property string $declared_amount
 * @property string $difference
 * @property string|null $batch_reference
 * @property CashSession $cashSession
 * @property PaymentMethod $paymentMethod
 */
#[Fillable([
    'cash_session_id',
    'payment_method_id',
    'expected_amount',
    'declared_amount',
    'difference',
    'batch_reference',
])]
class CashSessionClosureLine extends Model
{
    /** @use HasFactory<CashSessionClosureLineFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'declared_amount' => 'decimal:2',
            'difference' => 'decimal:2',
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
}
