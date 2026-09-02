<?php

namespace App\Models\Purchasing;

use App\Concerns\NormalizesUniqueAttributes;
use App\Enums\Purchasing\SupplierTaxCondition;
use Database\Factories\Purchasing\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * @property int $id
 * @property string $business_name
 * @property string $business_name_normalized
 * @property string $tax_id
 * @property SupplierTaxCondition $tax_condition
 * @property string|null $address
 * @property string|null $rubro
 * @property string|null $bank_account
 * @property string|null $commercial_terms
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'business_name',
    'tax_id',
    'tax_condition',
    'address',
    'rubro',
    'bank_account',
    'commercial_terms',
    'is_active',
])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    use NormalizesUniqueAttributes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'tax_condition' => SupplierTaxCondition::class,
        ];
    }

    /**
     * @param  Builder<Supplier>  $query
     * @return Builder<Supplier>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @return HasMany<SupplierVoucher, $this> */
    public function vouchers(): HasMany
    {
        return $this->hasMany(SupplierVoucher::class);
    }

    /** @return HasMany<PaymentOrder, $this> */
    public function paymentOrders(): HasMany
    {
        return $this->hasMany(PaymentOrder::class);
    }

    /**
     * Check if the supplier has associated transactions (vouchers, payment orders, etc.)
     * preventing destructive physical deletion and locking CUIT edits.
     */
    public function hasAssociatedRecords(): bool
    {
        if (Schema::hasTable('supplier_vouchers') && $this->vouchers()->exists()) {
            return true;
        }

        if (Schema::hasTable('payment_orders') && $this->paymentOrders()->exists()) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function uniqueAttributesToNormalize(): array
    {
        return [
            'business_name' => 'business_name_normalized',
        ];
    }
}
