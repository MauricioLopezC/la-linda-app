<?php

namespace App\Models\Sales;

use App\Models\Inventory\Warehouse;
use Database\Factories\Sales\PointOfSaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $number
 * @property int $warehouse_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('points_of_sale')]
#[Fillable(['number', 'warehouse_id', 'is_active'])]
class PointOfSale extends Model
{
    /** @use HasFactory<PointOfSaleFactory> */
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
     * Scope a query to only include active points of sale.
     *
     * @param  Builder<PointOfSale>  $query
     * @return Builder<PointOfSale>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the warehouse this point of sale discounts stock from.
     *
     * The branch a point of sale belongs to is always derived from
     * warehouse.branch_id — it has no branch_id column of its own.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
