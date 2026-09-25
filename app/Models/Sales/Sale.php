<?php

namespace App\Models\Sales;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Customers\Customer;
use App\Models\User;
use Database\Factories\Sales\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A sale being built at a point of sale (HU-039).
 *
 * The branch is not stored: it is derived from point_of_sale → warehouse → branch.
 *
 * @property int $id
 * @property int $point_of_sale_id
 * @property SaleChannel $channel
 * @property int $customer_id
 * @property int|null $user_id
 * @property Carbon $opened_at
 * @property SaleStatus $status
 * @property string $total_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property PointOfSale $pointOfSale
 * @property Customer $customer
 * @property User|null $user
 * @property Collection<int, SaleItem> $items
 */
#[Fillable([
    'point_of_sale_id',
    'channel',
    'customer_id',
    'user_id',
    'opened_at',
    'status',
    'total_amount',
])]
class Sale extends Model
{
    use ConvertsMoneyToCents;

    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'channel' => SaleChannel::Mostrador,
        'status' => SaleStatus::Open,
        'total_amount' => '0.00',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => SaleChannel::class,
            'status' => SaleStatus::class,
            'opened_at' => 'datetime',
            'total_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<PointOfSale, $this> */
    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<SaleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @param  Builder<Sale>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', SaleStatus::Open);
    }

    /**
     * Only an open sale accepts changes to its lines or its customer.
     */
    public function isOpen(): bool
    {
        return $this->status === SaleStatus::Open;
    }

    /**
     * Recompute total_amount as the sum of the line totals, in cents to avoid float drift.
     *
     * List prices are final prices with VAT included, so the total needs no VAT breakdown
     * (that is HU-057).
     */
    public function recalculateTotal(): void
    {
        $totalCents = $this->items()
            ->pluck('line_total')
            ->sum(fn (string $lineTotal): int => $this->moneyToCents($lineTotal));

        $this->update(['total_amount' => $this->centsToMoney($totalCents)]);
    }
}
