<?php

namespace App\Models\Inventory;

use App\Models\Purchasing\SupplierVoucher;
use App\Models\User;
use Database\Factories\Inventory\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Header of a stock movement. One row = one affected warehouse.
 *
 * A confirmed movement is immutable: there are no update or delete routes for it, and the table
 * has no updated_at column, so there is nowhere to store an edit either.
 *
 * @property int $id
 * @property int $stock_movement_type_id
 * @property int $warehouse_id
 * @property int|null $supplier_voucher_id
 * @property int|null $reversal_of_movement_id
 * @property string|null $notes
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property SupplierVoucher|null $supplierVoucher
 * @property StockMovement|null $reversalOf
 * @property StockMovement|null $reversal
 */
#[Fillable(['stock_movement_type_id', 'warehouse_id', 'supplier_voucher_id', 'reversal_of_movement_id', 'notes', 'user_id'])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    /**
     * The table has no updated_at column, so Eloquent must not try to write it.
     */
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the detail lines of this movement.
     *
     * @return HasMany<StockMovementItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockMovementItem::class);
    }

    /**
     * Get the type of this movement. Its code is what tells an adjustment from a transfer.
     *
     * @return BelongsTo<StockMovementType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(StockMovementType::class, 'stock_movement_type_id');
    }

    /**
     * Get the warehouse affected by this movement.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the user who registered this movement.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the supplier voucher that originated this movement.
     *
     * @return BelongsTo<SupplierVoucher, $this>
     */
    public function supplierVoucher(): BelongsTo
    {
        return $this->belongsTo(SupplierVoucher::class);
    }

    /**
     * Get the original movement this movement reverses.
     *
     * @return BelongsTo<StockMovement, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'reversal_of_movement_id');
    }

    /**
     * Get the reversal movement for this movement.
     *
     * @return HasOne<StockMovement, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(StockMovement::class, 'reversal_of_movement_id');
    }
}
