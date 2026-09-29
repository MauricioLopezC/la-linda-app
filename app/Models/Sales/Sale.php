<?php

namespace App\Models\Sales;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Customers\Customer;
use App\Models\Inventory\StockMovement;
use App\Models\User;
use Database\Factories\Sales\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A sale being built at a point of sale (HU-039).
 *
 * The branch is not stored: it is derived from point_of_sale → warehouse → branch. A counter
 * sale always belongs to the cash session it was opened in (a CHECK enforces it); only online
 * sales (EPIC-15) have no session.
 *
 * @property int $id
 * @property int $point_of_sale_id
 * @property SaleChannel $channel
 * @property int|null $cash_session_id
 * @property int $customer_id
 * @property int|null $user_id
 * @property Carbon $opened_at
 * @property SaleStatus $status
 * @property Carbon|null $confirmed_at
 * @property string $total_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property PointOfSale $pointOfSale
 * @property CashSession|null $cashSession
 * @property Customer $customer
 * @property User|null $user
 * @property Collection<int, SaleItem> $items
 * @property Collection<int, CashMovement> $cashMovements
 * @property Invoice|null $invoice
 * @property StockMovement|null $stockMovement
 */
#[Fillable([
    'point_of_sale_id',
    'channel',
    'cash_session_id',
    'customer_id',
    'user_id',
    'opened_at',
    'status',
    'confirmed_at',
    'total_amount',
])]
class Sale extends Model
{
    use ConvertsMoneyToCents;

    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'channel' => SaleChannel::Mostrador,
        'status' => SaleStatus::Open,
        'total_amount' => '0.00',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => SaleChannel::class,
            'status' => SaleStatus::class,
            'opened_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'total_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<PointOfSale, $this> */
    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class);
    }

    /** @return BelongsTo<CashSession, $this> */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<SaleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * The payments of the sale: one `venta` movement per payment method (EPIC-04).
     *
     * @return HasMany<CashMovement, $this>
     */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /** @return HasOne<Invoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * The "Salida por Venta" stock movement generated on confirmation (EPIC-06).
     *
     * @return HasOne<StockMovement, $this>
     */
    public function stockMovement(): HasOne
    {
        return $this->hasOne(StockMovement::class);
    }

    /**
     * @param  Builder<Sale>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', SaleStatus::Open);
    }

    /**
     * Only an open sale accepts changes to its lines or its customer.
     */
    public function isOpen(): bool
    {
        return $this->status === SaleStatus::Open;
    }

    /**
     * Recompute total_amount as the sum of the line totals, in cents to avoid float drift.
     *
     * List prices are final prices with VAT included, so the total needs no VAT breakdown:
     * each line keeps its own net and VAT (HU-063).
     */
    public function recalculateTotal(): void
    {
        $totalCents = $this->items()
            ->pluck('line_total')
            ->sum(fn (string $lineTotal): int => $this->moneyToCents($lineTotal));

        $this->update(['total_amount' => $this->centsToMoney($totalCents)]);
    }

    /**
     * Compute total net amount of the sale by summing item net amounts, in cents to avoid float drift.
     */
    public function netAmount(): string
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        $netCents = $items->sum(fn (SaleItem $item): int => $this->moneyToCents($item->net_amount));

        return $this->centsToMoney($netCents);
    }

    /**
     * Compute total VAT amount of the sale by summing item VAT amounts, in cents to avoid float drift.
     */
    public function vatAmount(): string
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        $vatCents = $items->sum(fn (SaleItem $item): int => $this->moneyToCents($item->vat_amount));

        return $this->centsToMoney($vatCents);
    }

    /**
     * Summary of net and VAT amounts grouped by VAT rate (HU-063).
     *
     * @return array<int, array{
     *     vat_rate_id: int,
     *     vat_rate: string,
     *     vat_rate_description: string,
     *     net_amount: string,
     *     vat_amount: string,
     *     total_amount: string,
     * }>
     */
    public function getVatBreakdown(): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->with('vatRate')->get();

        /** @var \Illuminate\Support\Collection<int, array{vat_rate_id: int, vat_rate: string, vat_rate_description: string, net_amount: string, vat_amount: string, total_amount: string}> $breakdown */
        $breakdown = $items
            ->groupBy('vat_rate_id')
            ->map(function (Collection $groupItems, int|string $vatRateId): array {
                /** @var SaleItem $first */
                $first = $groupItems->first();

                $netCents = $groupItems->sum(fn (SaleItem $item): int => $this->moneyToCents($item->net_amount));
                $vatCents = $groupItems->sum(fn (SaleItem $item): int => $this->moneyToCents($item->vat_amount));
                $totalCents = $groupItems->sum(fn (SaleItem $item): int => $this->moneyToCents($item->line_total));

                $vatRate = $first->vat_rate;
                $description = $first->vatRate->description;

                return [
                    'vat_rate_id' => (int) $vatRateId,
                    'vat_rate' => $vatRate,
                    'vat_rate_description' => $description,
                    'net_amount' => $this->centsToMoney($netCents),
                    'vat_amount' => $this->centsToMoney($vatCents),
                    'total_amount' => $this->centsToMoney($totalCents),
                ];
            })
            ->sortByDesc(fn (array $row): float => (float) $row['vat_rate'])
            ->values();

        return $breakdown->all();
    }
}
