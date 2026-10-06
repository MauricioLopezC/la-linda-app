<?php

namespace App\Models\Sales;

use App\Enums\Sales\InvoiceType;
use App\Models\Customers\Customer;
use App\Models\User;
use Database\Factories\Sales\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Invoice issued when a sale is confirmed (HU-042). An internal voucher without CAE until
 * SPIKE-01. Customer data and amounts are frozen at issue time, and it is immutable.
 *
 * @property int $id
 * @property int $sale_id
 * @property int $cash_session_id
 * @property int $point_of_sale_id
 * @property int $point_of_sale_number
 * @property InvoiceType $type
 * @property int $number
 * @property Carbon $issued_at
 * @property int $customer_id
 * @property string $customer_name
 * @property string $customer_tax_condition
 * @property string|null $customer_id_type
 * @property string|null $customer_id_number
 * @property string|null $customer_address
 * @property string $net_amount
 * @property string $vat_amount
 * @property string $total_amount
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Sale $sale
 * @property CashSession $cashSession
 * @property PointOfSale $pointOfSale
 * @property Customer $customer
 * @property User $user
 * @property Collection<int, InvoiceItem> $items
 */
#[Fillable([
    'sale_id',
    'cash_session_id',
    'point_of_sale_id',
    'point_of_sale_number',
    'type',
    'number',
    'issued_at',
    'customer_id',
    'customer_name',
    'customer_tax_condition',
    'customer_id_type',
    'customer_id_number',
    'customer_address',
    'net_amount',
    'vat_amount',
    'total_amount',
    'user_id',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'point_of_sale_number' => 'integer',
            'number' => 'integer',
            'issued_at' => 'datetime',
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<CashSession, $this> */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
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

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Standard voucher number formatted as POS (4 digits) - Number (8 digits).
     */
    public function formattedNumber(): string
    {
        return sprintf('%04d-%08d', $this->point_of_sale_number, $this->number);
    }

    /**
     * Full label including voucher type and formatted number (e.g. Factura B 0001-00000001).
     */
    public function voucherLabel(): string
    {
        return "{$this->type->label()} {$this->formattedNumber()}";
    }
}
