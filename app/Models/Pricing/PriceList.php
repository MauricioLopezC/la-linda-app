<?php

namespace App\Models\Pricing;

use App\Concerns\NormalizesUniqueAttributes;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Pricing\PriceListScope;
use App\Enums\Pricing\PriceListValidityStatus;
use Database\Factories\Pricing\PriceListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $name_normalized
 * @property PriceListScope $scope
 * @property PriceListChannel|null $channel
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 * @property bool $is_active
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'scope', 'channel', 'valid_from', 'valid_to', 'is_active'])]
class PriceList extends Model
{
    /** @use HasFactory<PriceListFactory> */
    use HasFactory;

    use NormalizesUniqueAttributes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scope' => PriceListScope::class,
            'channel' => PriceListChannel::class,
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<PriceList>  $query
     * @return Builder<PriceList>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Lists whose validity range contains today, regardless of their active flag.
     *
     * Date comparisons go through whereDate because the `date` cast persists a `Y-m-d H:i:s`
     * string on SQLite; a plain string comparison would skip a list that starts today.
     *
     * @param  Builder<PriceList>  $query
     * @return Builder<PriceList>
     */
    public function scopeCurrentlyValid(Builder $query): Builder
    {
        $today = Carbon::today()->toDateString();

        return $query->whereDate('valid_from', '<=', $today)
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $today));
    }

    /**
     * Base lists of a sales channel, the only ones subject to the one-per-channel rule.
     *
     * @param  Builder<PriceList>  $query
     * @return Builder<PriceList>
     */
    public function scopeForChannel(Builder $query, PriceListChannel|string $channel): Builder
    {
        return $query->where('scope', PriceListScope::Canal)->where('channel', $channel);
    }

    /**
     * Lists whose validity range intersects the given period, regardless of their active flag.
     *
     * @param  Builder<PriceList>  $query
     * @return Builder<PriceList>
     */
    public function scopeOverlapping(Builder $query, string $validFrom, ?string $validTo): Builder
    {
        return $query
            ->when($validTo !== null, fn (Builder $q) => $q->whereDate('valid_from', '<=', $validTo))
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $validFrom));
    }

    /**
     * Whether the general channel is covered without gaps from today onwards.
     *
     * The business must never be left without a fallback price, so it is not enough to have a
     * general list in effect today: the chain of active general lists has to run open-ended into
     * the future. Scheduling a successor that starts the day the current one ends is allowed.
     */
    public static function generalCoverageIsContinuous(): bool
    {
        /** @var Collection<int, PriceList> $generalLists */
        $generalLists = self::query()
            ->forChannel(PriceListChannel::General)
            ->active()
            ->orderBy('valid_from')
            ->get();

        $cursor = Carbon::today();

        foreach ($generalLists as $generalList) {
            if ($generalList->valid_from->gt($cursor)) {
                return false;
            }

            if ($generalList->valid_to === null) {
                return true;
            }

            if ($generalList->valid_to->gte($cursor)) {
                $cursor = $generalList->valid_to->copy()->addDay();
            }
        }

        return false;
    }

    /**
     * Get the article prices this list holds (HU-012).
     *
     * @return HasMany<PriceListItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function validityStatus(): PriceListValidityStatus
    {
        $today = Carbon::today();

        if ($this->valid_from->gt($today)) {
            return PriceListValidityStatus::Futura;
        }

        if ($this->valid_to !== null && $this->valid_to->lt($today)) {
            return PriceListValidityStatus::Vencida;
        }

        return PriceListValidityStatus::Vigente;
    }

    public function isInUse(): bool
    {
        // TODO: check Sale once the sales/invoicing module (Sprint 4) is implemented.
        return false;
    }

    /** @return array<string, string> */
    protected function uniqueAttributesToNormalize(): array
    {
        return [
            'name' => 'name_normalized',
        ];
    }
}
