<?php

namespace App\Models\Purchasing;

use App\Models\User;
use Database\Factories\Purchasing\VoucherApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One imputation of a credit/debit note onto an invoice (HU-054).
 *
 * Immutable: there is no update or delete route and the table has no updated_at column, so a
 * correction is a counter-entry, never an edit.
 *
 * @property int $id
 * @property int $source_voucher_id
 * @property int $target_voucher_id
 * @property string $amount
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property SupplierVoucher $sourceVoucher
 * @property SupplierVoucher $targetVoucher
 * @property User $user
 */
#[Fillable(['source_voucher_id', 'target_voucher_id', 'amount', 'user_id'])]
class VoucherApplication extends Model
{
    /** @use HasFactory<VoucherApplicationFactory> */
    use HasFactory;

    /**
     * The table has no updated_at column, so Eloquent must not try to write it.
     */
    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The credit or debit note being imputed.
     *
     * @return BelongsTo<SupplierVoucher, $this>
     */
    public function sourceVoucher(): BelongsTo
    {
        return $this->belongsTo(SupplierVoucher::class, 'source_voucher_id');
    }

    /**
     * The invoice whose balance this application moves.
     *
     * @return BelongsTo<SupplierVoucher, $this>
     */
    public function targetVoucher(): BelongsTo
    {
        return $this->belongsTo(SupplierVoucher::class, 'target_voucher_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
