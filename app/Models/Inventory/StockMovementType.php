<?php

namespace App\Models\Inventory;

use App\Concerns\NormalizesUniqueAttributes;
use Database\Factories\Inventory\StockMovementTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $name_normalized
 * @property string $code
 * @property int $sign
 * @property string|null $description
 * @property bool $is_system
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'code', 'sign', 'description', 'is_system', 'is_active'])]
class StockMovementType extends Model
{
    /** @use HasFactory<StockMovementTypeFactory> */
    use HasFactory;

    use NormalizesUniqueAttributes;

    /**
     * Standard system codes.
     */
    public const CODE_PURCHASE_ENTRY = 'purchase_entry';

    public const CODE_SALE_EXIT = 'sale_exit';

    public const CODE_CUSTOMER_RETURN = 'customer_return';

    public const CODE_WAREHOUSE_TRANSFER = 'warehouse_transfer';

    public const CODE_INVENTORY_ADJUSTMENT = 'inventory_adjustment';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sign' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active movement types.
     *
     * @param  Builder<StockMovementType>  $query
     * @return Builder<StockMovementType>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include system-defined types.
     *
     * @param  Builder<StockMovementType>  $query
     * @return Builder<StockMovementType>
     */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope a query to only include user-created custom types.
     *
     * @param  Builder<StockMovementType>  $query
     * @return Builder<StockMovementType>
     */
    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('is_system', false);
    }

    /**
     * Check if this movement type is currently in use in recorded movements.
     */
    public function isInUse(): bool
    {
        // Future relation with StockMovement (HU-017 / HU-018)
        // When stock_movements table exists, return $this->stockMovements()->exists();
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
