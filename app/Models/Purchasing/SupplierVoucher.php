<?php

namespace App\Models\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
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
 * @property string $net_amount
 * @property string $vat_amount
 * @property string $other_taxes_amount
 * @property string $total_amount
 * @property SupplierVoucherStatus $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Supplier $supplier
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
    'net_amount',
    'vat_amount',
    'other_taxes_amount',
    'total_amount',
    'status',
    'notes',
])]
class SupplierVoucher extends Model
{
    use ConvertsMoneyToCents;

    /** @use HasFactory<SupplierVoucherFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'letter' => SupplierVoucherLetter::A->value,
        'other_taxes_amount' => '0.00',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => SupplierVoucherType::class,
            'letter' => SupplierVoucherLetter::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'other_taxes_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'status' => SupplierVoucherStatus::class,
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
     * Imputations where this voucher is the credit/debit note being applied.
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
                ->whereColumn('payment_order_items.supplier_voucher_id', 'supplier_vouchers.id'),
            'credit_notes_applied_sum' => VoucherApplication::query()
                ->selectRaw('coalesce(sum(voucher_applications.amount), 0)')
                ->join('supplier_vouchers as source_voucher', 'source_voucher.id', '=', 'voucher_applications.source_voucher_id')
                ->whereColumn('voucher_applications.target_voucher_id', 'supplier_vouchers.id')
                ->where('source_voucher.type', SupplierVoucherType::CreditNote->value),
            'debit_notes_applied_sum' => VoucherApplication::query()
                ->selectRaw('coalesce(sum(voucher_applications.amount), 0)')
                ->join('supplier_vouchers as source_voucher', 'source_voucher.id', '=', 'voucher_applications.source_voucher_id')
                ->whereColumn('voucher_applications.target_voucher_id', 'supplier_vouchers.id')
                ->where('source_voucher.type', SupplierVoucherType::DebitNote->value),
            'note_applied_sum' => VoucherApplication::query()
                ->selectRaw('coalesce(sum(amount), 0)')
                ->whereColumn('voucher_applications.source_voucher_id', 'supplier_vouchers.id'),
        ]);
    }

    /**
     * Pending balance of this invoice, derived and never stored:
     * total_amount − Σ payments imputed − Σ credit notes applied + Σ debit notes applied.
     *
     * Meaningful for invoices; for a credit/debit note use {@see unappliedAmount()}.
     */
    public function pendingBalance(): string
    {
        $cents = $this->moneyToCents($this->total_amount)
            - $this->balanceAggregateCents(
                'payments_applied_sum',
                fn () => $this->paymentOrderItems()->sum('amount_applied'),
            )
            - $this->balanceAggregateCents(
                'credit_notes_applied_sum',
                fn () => $this->applicationsReceived()
                    ->whereRelation('sourceVoucher', 'type', SupplierVoucherType::CreditNote->value)
                    ->sum('amount'),
            )
            + $this->balanceAggregateCents(
                'debit_notes_applied_sum',
                fn () => $this->applicationsReceived()
                    ->whereRelation('sourceVoucher', 'type', SupplierVoucherType::DebitNote->value)
                    ->sum('amount'),
            );

        return $this->centsToMoney($cents);
    }

    /**
     * Portion of this credit/debit note that has not yet been imputed to any invoice:
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
     * invoice, the unapplied amount for a credit/debit note. Both HU-054 and HU-027 validate
     * their imputations against this figure.
     */
    public function outstandingAmount(): string
    {
        return $this->type->isInvoice() ? $this->pendingBalance() : $this->unappliedAmount();
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
            && (float) $this->pendingBalance() > 0;
    }
}
