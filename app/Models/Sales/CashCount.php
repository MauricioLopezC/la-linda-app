<?php

namespace App\Models\Sales;

use App\Enums\Sales\CashCountMoment;
use App\Enums\Sales\CashDenomination;
use Database\Factories\Sales\CashCountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many bills of one denomination were counted at the opening or closing of a session.
 *
 * @property int $id
 * @property int $cash_session_id
 * @property CashCountMoment $moment
 * @property string $denomination
 * @property int $quantity
 * @property CashSession $cashSession
 */
#[Fillable(['cash_session_id', 'moment', 'denomination', 'quantity'])]
class CashCount extends Model
{
    /** @use HasFactory<CashCountFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'moment' => CashCountMoment::class,
            'denomination' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<CashSession, $this> */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function denomination(): CashDenomination
    {
        return CashDenomination::from((int) $this->denomination);
    }
}
