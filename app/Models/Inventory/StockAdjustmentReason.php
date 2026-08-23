<?php

namespace App\Models\Inventory;

use App\Concerns\NormalizesUniqueAttributes;
use Database\Factories\Inventory\StockAdjustmentReasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $name_normalized
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'is_active'])]
class StockAdjustmentReason extends Model
{
    /** @use HasFactory<StockAdjustmentReasonFactory> */
    use HasFactory;

    use NormalizesUniqueAttributes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active reasons.
     *
     * @param  Builder<StockAdjustmentReason>  $query
     * @return Builder<StockAdjustmentReason>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the movements recorded with this adjustment reason.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Check if this adjustment reason is currently used in recorded adjustments.
     */
    public function isInUse(): bool
    {
        return $this->stockMovements()->exists();
    }

    /** @return array<string, string> */
    protected function uniqueAttributesToNormalize(): array
    {
        return [
            'name' => 'name_normalized',
        ];
    }
}
