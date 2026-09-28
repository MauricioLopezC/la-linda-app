<?php

namespace App\Models\Sales;

use App\Enums\Sales\CashCountMoment;
use App\Enums\Sales\CashSessionStatus;
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

    public function isOpen(): bool
    {
        return $this->status === CashSessionStatus::Open;
    }
}
