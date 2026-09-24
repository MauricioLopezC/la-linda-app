<?php

namespace App\Models\Customers;

use App\Concerns\NormalizesUniqueAttributes;
use App\Enums\Customers\CustomerIdType;
use App\Enums\Customers\CustomerTaxCondition;
use App\Enums\Customers\PersonType;
use App\Models\Pricing\PriceList;
use App\Rules\Customers\ValidCuit;
use Database\Factories\Customers\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @property int $id
 * @property PersonType $person_type
 * @property string $name
 * @property string $name_normalized
 * @property CustomerIdType $id_type
 * @property string|null $id_number
 * @property CustomerTaxCondition $tax_condition
 * @property int|null $price_list_id
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_active
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PriceList|null $priceList
 */
#[Fillable([
    'person_type',
    'name',
    'id_type',
    'id_number',
    'tax_condition',
    'price_list_id',
    'address',
    'phone',
    'email',
    'is_active',
    'is_default',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use NormalizesUniqueAttributes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
        'is_default' => false,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'person_type' => PersonType::class,
            'id_type' => CustomerIdType::class,
            'tax_condition' => CustomerTaxCondition::class,
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Check if the customer is the immutable default customer (Consumidor Final).
     */
    public function isProtected(): bool
    {
        return (bool) $this->is_default;
    }

    /**
     * Format the customer's identification number (with hyphens for CUIT).
     */
    public function formattedIdNumber(): ?string
    {
        if ($this->id_number === null || $this->id_number === '') {
            return null;
        }

        if ($this->id_type === CustomerIdType::Cuit) {
            return ValidCuit::format($this->id_number) ?? $this->id_number;
        }

        return $this->id_number;
    }

    /**
     * Check if the customer has associated sales or transactions preventing destructive physical deletion.
     */
    public function hasAssociatedRecords(): bool
    {
        if (Schema::hasTable('sales') && DB::table('sales')->where('customer_id', $this->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * The preferential price list assigned to this customer (HU-022).
     *
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * @return array<string, string>
     */
    protected function uniqueAttributesToNormalize(): array
    {
        return [
            'name' => 'name_normalized',
        ];
    }
}
