<?php

namespace App\Models\Ecommerce;

use App\Models\Customers\Customer;
use Database\Factories\Ecommerce\CustomerAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Online store login of a customer (EPIC-11). It authenticates through its own `customer`
 * guard: internal staff are Users, shoppers are never.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $email
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Customer $customer
 */
#[Fillable(['customer_id', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class CustomerAccount extends Authenticatable
{
    /** @use HasFactory<CustomerAccountFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Stored trimmed and lowercased, so the UNIQUE index ignores case on every database.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Str::of($value)->trim()->lower()->toString(),
        );
    }
}
