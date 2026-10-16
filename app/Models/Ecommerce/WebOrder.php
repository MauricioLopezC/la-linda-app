<?php

namespace App\Models\Ecommerce;

use App\Enums\Ecommerce\DeliveryMethod;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Customers\Customer;
use App\Models\Organization\Branch;
use App\Models\Sales\Sale;
use App\Models\User;
use Database\Factories\Ecommerce\WebOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * An order placed from the online store (HU-062), with its delivery (HU-049), its Mercado Pago
 * payment (HU-050), its confirmed online sale (EPIC-15) and its warehouse preparation/delivery
 * audit trail (EPIC-17).
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
 * @property Carbon|null $stock_reserved_until
 * @property string|null $payment_reservation_token
 * @property string|null $mp_init_point
 * @property string|null $mp_sandbox_init_point
 * @property numeric-string|null $paid_amount
 * @property Carbon|null $paid_at
 * @property Carbon|null $preparation_started_at
 * @property int|null $prepared_by
 * @property Carbon|null $delivered_at
 * @property int|null $delivered_by
 * @property string|null $notes
 * @property Carbon $placed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Customer $customer
 * @property Branch|null $pickupBranch
 * @property User|null $preparedBy
 * @property User|null $deliveredBy
 * @property Sale|null $sale
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
    'stock_reserved_until',
    'payment_reservation_token',
    'mp_init_point',
    'mp_sandbox_init_point',
    'paid_amount',
    'paid_at',
    'preparation_started_at',
    'prepared_by',
    'delivered_at',
    'delivered_by',
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
            'stock_reserved_until' => 'datetime',
            'preparation_started_at' => 'datetime',
            'delivered_at' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /** @return BelongsTo<User, $this> */
    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /** @return HasOne<Sale, $this> */
    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
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

    /**
     * Status label adjusted for delivery method ("Despachado" when shipping and delivered).
     */
    public function statusLabel(): string
    {
        return $this->status->label($this->delivery_method);
    }
}
