<?php

namespace App\Models\Sales;

use App\Enums\Sales\CashCountMoment;
use App\Enums\Sales\CashMovementType;
use App\Enums\Sales\CashSessionStatus;
use App\Enums\Sales\PaymentMethodKind;
use App\Models\User;
use Database\Factories\Sales\CashSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A cash session: a cashier's shift at a point of sale (HU-057). Its id is the "cash id" every
 * sale and every money movement of the shift belongs to. A point of sale and a user each have
 * at most one open session (partial unique indexes).
 *
 * @property int $id
 * @property int $point_of_sale_id
 * @property int $user_id
 * @property CashSessionStatus $status
 * @property Carbon $opened_at
 * @property string $opening_amount
 * @property Carbon|null $closed_at
 * @property string|null $closing_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property PointOfSale $pointOfSale
 * @property User $user
 * @property Collection<int, CashCount> $counts
 * @property Collection<int, CashMovement> $movements
 * @property Collection<int, CashSessionClosureLine> $closureLines
 * @property Collection<int, Sale> $sales
 */
#[Fillable([
    'point_of_sale_id',
    'user_id',
    'status',
    'opened_at',
    'opening_amount',
    'closed_at',
    'closing_notes',
])]
class CashSession extends Model
{
    /** @use HasFactory<CashSessionFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => CashSessionStatus::Open,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => CashSessionStatus::class,
            'opened_at' => 'datetime',
            'opening_amount' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PointOfSale, $this> */
    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CashCount, $this> */
    public function counts(): HasMany
    {
        return $this->hasMany(CashCount::class);
    }

    /** @return HasMany<CashCount, $this> */
    public function openingCounts(): HasMany
    {
        return $this->counts()->where('moment', CashCountMoment::Opening);
    }

    /** @return HasMany<CashCount, $this> */
    public function closingCounts(): HasMany
    {
        return $this->counts()->where('moment', CashCountMoment::Closing);
    }

    /** @return HasMany<CashMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /** @return HasMany<CashSessionClosureLine, $this> */
    public function closureLines(): HasMany
    {
        return $this->hasMany(CashSessionClosureLine::class);
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @param  Builder<CashSession>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', CashSessionStatus::Open);
    }

    /**
     * The open session of a cashier (HU-057). A user has at most one, so `->first()` is the
     * session: HU-039 takes the point of sale of a counter sale from it, and the layout shows it.
     *
     *     CashSession::query()->openForUser($userId)->first();
     *
     * @param  Builder<CashSession>  $query
     */
    public function scopeOpenForUser(Builder $query, int $userId): void
    {
        $query->open()->where('user_id', $userId);
    }

    public function isOpen(): bool
    {
        return $this->status === CashSessionStatus::Open;
    }

    /**
     * The expected cash in the drawer according to recorded cash movements (HU-058 / HU-060).
     * Opening, cash sales and cash incomes add up; cash expenses subtract.
     *
     * @return numeric-string
     */
    public function expectedCash(): string
    {
        $balance = DB::table('cash_movements')
            ->join('payment_methods', 'payment_methods.id', '=', 'cash_movements.payment_method_id')
            ->where('cash_movements.cash_session_id', $this->id)
            ->where('payment_methods.kind', PaymentMethodKind::Cash->value)
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN cash_movements.type IN ('apertura', 'venta', 'ingreso') THEN cash_movements.amount
                        WHEN cash_movements.type = 'egreso' THEN -cash_movements.amount
                        ELSE 0
                    END
                ), 0) as balance
            ")
            ->value('balance');

        return number_format((float) ($balance ?? 0), 2, '.', '');
    }

    /**
     * Breakdown of cash totals by category for this shift.
     *
     * @return array{
     *     opening_amount: string,
     *     sales_cash_amount: string,
     *     income_amount: string,
     *     expense_amount: string,
     *     expected_cash: string
     * }
     */
    public function movementsSummary(): array
    {
        $rows = DB::table('cash_movements')
            ->join('payment_methods', 'payment_methods.id', '=', 'cash_movements.payment_method_id')
            ->where('cash_movements.cash_session_id', $this->id)
            ->selectRaw('cash_movements.type, payment_methods.kind, SUM(cash_movements.amount) as total')
            ->groupBy('cash_movements.type', 'payment_methods.kind')
            ->get();

        $opening = '0.00';
        $salesCash = '0.00';
        $income = '0.00';
        $expense = '0.00';

        foreach ($rows as $row) {
            $amount = (float) $row->total;
            if ($row->type === CashMovementType::Opening->value) {
                $opening = number_format($amount, 2, '.', '');
            } elseif ($row->type === CashMovementType::Sale->value && $row->kind === PaymentMethodKind::Cash->value) {
                $salesCash = number_format($amount, 2, '.', '');
            } elseif ($row->type === CashMovementType::Income->value) {
                $income = number_format($amount, 2, '.', '');
            } elseif ($row->type === CashMovementType::Expense->value) {
                $expense = number_format($amount, 2, '.', '');
            }
        }

        if ($opening === '0.00' && (float) $this->opening_amount > 0) {
            $opening = number_format((float) $this->opening_amount, 2, '.', '');
        }

        return [
            'opening_amount' => $opening,
            'sales_cash_amount' => $salesCash,
            'income_amount' => $income,
            'expense_amount' => $expense,
            'expected_cash' => $this->expectedCash(),
        ];
    }
}
