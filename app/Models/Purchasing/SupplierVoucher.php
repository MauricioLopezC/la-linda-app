<?php

namespace App\Models\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Purchasing\PaymentOrderStatus;
use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\User;
use Closure;
use Database\Factories\Purchasing\SupplierVoucherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_id
 * @property SupplierVoucherType $type
 * @property SupplierVoucherLetter $letter
 * @property string $point_of_sale
 * @property string $number
 * @property Carbon $issue_date
 * @property Carbon|null $due_date
 * @property string $total_amount
 * @property SupplierVoucherStatus $status
 * @property string|null $notes
 * @property Carbon|null $annulled_at
 * @property int|null $annulled_by
 * @property string|null $annulment_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Supplier $supplier
 * @property Collection<int, SupplierVoucherItem> $items
 * @property User|null $annulledByUser
 * @property Collection<int, PaymentOrderItem> $paymentOrderItems
 * @property Collection<int, VoucherApplication> $applicationsMade
 * @property Collection<int, VoucherApplication> $applicationsReceived
 */
#[Fillable([
    'supplier_id',
    'type',
    'letter',
    'point_of_sale',
    'number',
    'issue_date',
    'due_date',
    'total_amount',
    'status',
    'notes',
    'annulled_at',
    'annulled_by',
    'annulment_reason',
])]
class SupplierVoucher extends Model
{
    use ConvertsMoneyToCents;

    /** @use HasFactory<SupplierVoucherFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['letter' => SupplierVoucherLetter::A->value];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => SupplierVoucherType::class,
            'letter' => SupplierVoucherLetter::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'total_amount' => 'decimal:2',
            'status' => SupplierVoucherStatus::class,
            'annulled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<SupplierVoucherItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierVoucherItem::class)->orderBy('position');
    }

    /** @return BelongsTo<User, $this> */
    public function annulledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulled_by');
    }

    /**
     * Payment order lines that paid this voucher down (this voucher acting as an invoice).
     *
     * @return HasMany<PaymentOrderItem, $this>
     */
    public function paymentOrderItems(): HasMany
    {
        return $this->hasMany(PaymentOrderItem::class);
    }

    /**
     * Imputations where this voucher is the credit note being applied.
     *
     * @return HasMany<VoucherApplication, $this>
     */
    public function applicationsMade(): HasMany
    {
        return $this->hasMany(VoucherApplication::class, 'source_voucher_id');
    }

    /**
     * Imputations where this voucher is the invoice receiving a note.
     *
     * @return HasMany<VoucherApplication, $this>
     */
    public function applicationsReceived(): HasMany
    {
        return $this->hasMany(VoucherApplication::class, 'target_voucher_id');
    }

    /**
     * Attach, as sub-selected columns, the per-voucher aggregates the balance derivation needs, so
     * a listing resolves every balance in one query instead of one query per row. pendingBalance()
     * and unappliedAmount() pick these up automatically when they are present.
     *
     * @param  Builder<SupplierVoucher>  $query
     */
    public function scopeWithBalanceAggregates(Builder $query): void
    {
        $query->select('supplier_vouchers.*')->addSelect([
            'payments_applied_sum' => PaymentOrderItem::query()
                ->selectRaw('coalesce(sum(amount_applied), 0)')
                ->join('payment_orders', 'payment_orders.id', '=', 'payment_order_items.payment_order_id')
                ->where('payment_orders.status', '!=', PaymentOrderStatus::Cancelled->value)
                ->whereColumn('payment_order_items.supplier_voucher_id', 'supplier_vouchers.id'),
            'credit_notes_applied_sum' => VoucherApplication::query()
                ->selectRaw('coalesce(sum(voucher_applications.amount), 0)')
                ->join('supplier_vouchers as source_voucher', 'source_voucher.id', '=', 'voucher_applications.source_voucher_id')
                ->whereColumn('voucher_applications.target_voucher_id', 'supplier_vouchers.id')
                ->where('source_voucher.type', SupplierVoucherType::CreditNote->value),
            'note_applied_sum' => VoucherApplication::query()
                ->selectRaw('coalesce(sum(amount), 0)')
                ->whereColumn('voucher_applications.source_voucher_id', 'supplier_vouchers.id'),
        ]);
    }

    /**
     * Pending balance of this invoice or debit note, derived and never stored:
     * total_amount − Σ payments imputed − Σ credit notes applied.
     *
     * Meaningful for invoices and debit notes; for a credit note use {@see unappliedAmount()}.
     */
    public function pendingBalance(): string
    {
        $cents = $this->moneyToCents($this->total_amount)
            - $this->balanceAggregateCents(
                'payments_applied_sum',
                fn () => $this->paymentOrderItems()
                    ->whereHas('paymentOrder', fn (Builder $query): Builder => $query
                        ->where('status', '!=', PaymentOrderStatus::Cancelled->value))
                    ->sum('amount_applied'),
            )
            - $this->balanceAggregateCents(
                'credit_notes_applied_sum',
                fn () => $this->applicationsReceived()
                    ->whereRelation('sourceVoucher', 'type', SupplierVoucherType::CreditNote->value)
                    ->sum('amount'),
            );

        return $this->centsToMoney($cents);
    }

    /**
     * Portion of this credit note that has not yet been imputed to any invoice:
     * total_amount − Σ voucher_applications.amount whose source is this note.
     */
    public function unappliedAmount(): string
    {
        $cents = $this->moneyToCents($this->total_amount)
            - $this->balanceAggregateCents(
                'note_applied_sum',
                fn () => $this->applicationsMade()->sum('amount'),
            );

        return $this->centsToMoney($cents);
    }

    /**
     * Amount that still has to move for this voucher to be settled: the pending balance for an
     * invoice or debit note, the unapplied amount for a credit note. Both HU-054 and HU-027 validate
     * their imputations against this figure.
     */
    public function outstandingAmount(): string
    {
        if ($this->status === SupplierVoucherStatus::Cancelled) {
            return '0.00';
        }

        return $this->type->isCreditNote() ? $this->unappliedAmount() : $this->pendingBalance();
    }

    public function itemsTotal(): string
    {
        $this->loadMissing('items');
        $cents = $this->items->sum(
            fn (SupplierVoucherItem $item): int => $this->moneyToCents((string) $item->line_total)
        );

        return $this->centsToMoney($cents);
    }

    public function differenceAmount(): string
    {
        return $this->centsToMoney(
            $this->moneyToCents((string) $this->total_amount) - $this->moneyToCents($this->itemsTotal())
        );
    }

    /**
     * Read one balance component in integer cents: the pre-selected aggregate column when the
     * scope loaded it, otherwise the fallback aggregate query for this single row.
     *
     * @param  Closure(): (string|int|float|null)  $fallback
     */
    private function balanceAggregateCents(string $preloadedAttribute, Closure $fallback): int
    {
        $raw = array_key_exists($preloadedAttribute, $this->attributes)
            ? $this->attributes[$preloadedAttribute]
            : $fallback();

        return $this->moneyToCents(number_format((float) ($raw ?? 0), 2, '.', ''));
    }

    public function isOverdue(?Carbon $referenceDate = null): bool
    {
        return $this->due_date !== null
            && $this->due_date->isBefore($referenceDate ?? today())
            && $this->status !== SupplierVoucherStatus::Cancelled
            && (float) $this->outstandingAmount() > 0;
    }

    public function canBeAnnulled(): bool
    {
        if ($this->status === SupplierVoucherStatus::Cancelled) {
            return false;
        }

        $hasActivePaymentOrders = $this->paymentOrderItems()
            ->whereHas('paymentOrder', fn (Builder $query): Builder => $query
                ->where('status', '!=', PaymentOrderStatus::Cancelled->value))
            ->exists();

        return ! $hasActivePaymentOrders
            && ! $this->applicationsMade()->exists()
            && ! $this->applicationsReceived()->exists();
    }
}
