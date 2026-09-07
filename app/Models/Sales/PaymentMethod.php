<?php

namespace App\Models\Sales;

use App\Concerns\NormalizesUniqueAttributes;
use Database\Factories\Sales\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @property int $id
 * @property string $name
 * @property string $name_normalized
 * @property bool $is_enabled_online
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'is_enabled_online', 'is_active'])]
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory;

    use NormalizesUniqueAttributes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_enabled_online' => false,
        'is_active' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_enabled_online' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<PaymentMethod>  $query
     * @return Builder<PaymentMethod>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isInUse(): bool
    {
        if (Schema::hasTable('payment_order_methods') && DB::table('payment_order_methods')->where('payment_method_id', $this->id)->exists()) {
            return true;
        }

        if (Schema::hasTable('payment_orders') && Schema::hasColumn('payment_orders', 'payment_method_id') && DB::table('payment_orders')->where('payment_method_id', $this->id)->exists()) {
            return true;
        }

        if (Schema::hasTable('sales') && DB::table('sales')->where('payment_method_id', $this->id)->exists()) {
            return true;
        }

        return false;
    }

    /** @return array<string, string> */
    protected function uniqueAttributesToNormalize(): array
    {
        return [
            'name' => 'name_normalized',
        ];
    }
}
