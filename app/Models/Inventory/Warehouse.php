<?php

namespace App\Models\Inventory;

use App\Models\Organization\Branch;
use App\Models\Sales\PointOfSale;
use Database\Factories\Inventory\WarehouseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $branch_id
 * @property bool $is_online_channel
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'branch_id', 'is_online_channel', 'is_active'])]
class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_online_channel' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active warehouses.
     *
     * @param  Builder<Warehouse>  $query
     * @return Builder<Warehouse>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the branch this warehouse belongs to.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get all points of sale that discount stock from this warehouse.
     *
     * @return HasMany<PointOfSale, $this>
     */
    public function pointsOfSale(): HasMany
    {
        return $this->hasMany(PointOfSale::class);
    }

    /**
     * Get all stock records for this warehouse.
     *
     * @return HasMany<WarehouseStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    /**
     * Check if this warehouse has registered stock or movements that block deactivation.
     */
    public function hasRegisteredStock(): bool
    {
        return $this->stocks()->exists();
    }
}
