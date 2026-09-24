<?php

namespace App\Models\Sales;

use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Customers\Customer;
use App\Models\User;
use Database\Factories\Sales\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /**
     * @param  Builder<Sale>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', SaleStatus::Open);
    }

    /**
     * Only an open sale accepts changes.
     */
    public function isOpen(): bool
    {
        return $this->status === SaleStatus::Open;
    }
}
