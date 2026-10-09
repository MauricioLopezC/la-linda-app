<?php

namespace App\Models\Ecommerce;

use App\Enums\Ecommerce\DeliveryMethod;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Customers\Customer;
use App\Models\Organization\Branch;
use Database\Factories\Ecommerce\WebOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An order placed from the online store (HU-062), with its delivery (HU-049) and its Mercado
 * Pago payment (HU-050). It is not a sale: EPIC-15 turns a paid order into one.
 *
 * @property int $id
 * @property int $number
 * @property int $customer_id
 * @property WebOrderStatus $status
 * @property DeliveryMethod $delivery_method
 * @property int|null $pickup_branch_id
 * @property string|null $shipping_address
 * @property string|null $shipping_notes
 * @property numeric-string $items_amount
 * @property numeric-string $shipping_cost
 * @property numeric-string $total_amount
 * @property string|null $mp_preference_id
 * @property string|null $mp_payment_id
 * @property numeric-string|null $paid_amount
 * @property Carbon|null $paid_at
 * @property string|null $notes
 * @property Carbon $placed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Customer $customer
 * @property Branch|null $pickupBranch
 * @property Collection<int, WebOrderItem> $items
 */
#[Fillable([
    'number',
    'customer_id',
    'status',
    'delivery_method',
    'pickup_branch_id',
    'shipping_address',
    'shipping_notes',
    'items_amount',
    'shipping_cost',
    'total_amount',
    'mp_preference_id',
    'mp_payment_id',
    'paid_amount',
    'paid_at',
    'notes',
    'placed_at',
])]
class WebOrder extends Model
{
    /** @use HasFactory<WebOrderFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => WebOrderStatus::Pending,
        'delivery_method' => DeliveryMethod::Pickup,
        'shipping_cost' => '0.00',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => WebOrderStatus::class,
            'delivery_method' => DeliveryMethod::class,
            'items_amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'placed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function pickupBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'pickup_branch_id');
    }

    /** @return HasMany<WebOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(WebOrderItem::class);
    }

    /**
     * Order number as shown to the customer (e.g. 00000042).
     */
    public function formattedNumber(): string
    {
        return sprintf('%08d', $this->number);
    }
}
