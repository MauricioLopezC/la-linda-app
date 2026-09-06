<?php

namespace App\Models\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Purchasing\PurchaseOrderStatus;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Database\Factories\Purchasing\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_id
 * @property int $warehouse_id
 * @property string $order_number
 * @property string|null $payment_terms
 * @property Carbon $issue_date
 * @property Carbon|null $expected_delivery_date
 * @property string $total_amount
 * @property PurchaseOrderStatus $status
 * @property string|null $notes
 * @property int|null $user_id
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Supplier $supplier
 * @property Warehouse $warehouse
 * @property Collection<int, PurchaseOrderItem> $items
 * @property User|null $user
 * @property User|null $cancelledByUser
 */
#[Fillable([
    'supplier_id',
    'warehouse_id',
    'order_number',
    'payment_terms',
    'issue_date',
    'expected_delivery_date',
    'total_amount',
    'status',
    'notes',
    'user_id',
    'cancelled_at',
    'cancelled_by',
    'cancellation_reason',
])]
class PurchaseOrder extends Model
{
    use ConvertsMoneyToCents;

    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => PurchaseOrderStatus::Draft,
        'total_amount' => '0.00',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date:Y-m-d',
            'expected_delivery_date' => 'date:Y-m-d',
            'total_amount' => 'decimal:2',
            'status' => PurchaseOrderStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return HasMany<PurchaseOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isDraft(): bool
    {
        return $this->status === PurchaseOrderStatus::Draft;
    }

    public function isIssued(): bool
    {
        return $this->status === PurchaseOrderStatus::Issued;
    }

    public function isCancelled(): bool
    {
        return $this->status === PurchaseOrderStatus::Cancelled;
    }

    public function canBeEdited(): bool
    {
        return $this->isDraft();
    }

    public function canBeIssued(): bool
    {
        return $this->isDraft() && $this->items()->exists();
    }

    public function canBeCancelled(): bool
    {
        return $this->isIssued();
    }

    /**
     * Recalculate and update the total amount from its items.
     */
    public function recalculateTotal(): void
    {
        $totalCents = 0;
        foreach ($this->items as $item) {
            $totalCents += $this->moneyToCents((string) $item->line_total);
        }

        $this->update([
            'total_amount' => $this->centsToMoney($totalCents),
        ]);
    }
}
