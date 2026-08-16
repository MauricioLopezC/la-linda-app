<?php

namespace App\Models\Organization;

use App\Models\Inventory\Warehouse;
use App\Models\Sales\PointOfSale;
use Database\Factories\Organization\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $address
 * @property string|null $phone
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'address', 'phone', 'is_active'])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
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
     * Scope a query to only include active branches.
     *
     * @param  Builder<Branch>  $query
     * @return Builder<Branch>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get all warehouses that belong to this branch.
     *
     * @return HasMany<Warehouse, $this>
     */
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    /**
     * Get all points of sale that belong to this branch, through its warehouses.
     *
     * @return HasManyThrough<PointOfSale, Warehouse, $this>
     */
    public function pointsOfSale(): HasManyThrough
    {
        return $this->hasManyThrough(PointOfSale::class, Warehouse::class);
    }

    /**
     * Check if this branch has registered stock or movements that block deactivation.
     */
    public function hasRegisteredStock(): bool
    {
        // Future relation with WarehouseStock/StockMovement through warehouses (HU-017).
        // When those tables exist, check: $this->warehouses->contains(fn (Warehouse $w) => $w->hasRegisteredStock())
        return false;
    }
}
