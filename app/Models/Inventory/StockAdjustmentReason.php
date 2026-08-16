<?php

namespace App\Models\Inventory;

use Database\Factories\Inventory\StockAdjustmentReasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
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
     * Check if this adjustment reason is currently used in recorded adjustments.
     */
    public function isInUse(): bool
    {
        // Future relation with StockAdjustment (HU-017)
        // When stock_adjustments table exists, return $this->stockAdjustments()->exists();
        return false;
    }
}
