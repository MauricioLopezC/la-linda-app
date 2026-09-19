<?php

namespace App\Models\Pricing;

use App\Concerns\NormalizesUniqueAttributes;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Pricing\PriceListValidityStatus;
use Database\Factories\Pricing\PriceListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $name_normalized
 * @property PriceListChannel $channel
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 * @property bool $is_active
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'channel', 'valid_from', 'valid_to', 'is_active'])]
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
     * @param  Builder<PriceList>  $query
     * @return Builder<PriceList>
     */
    public function scopeVigente(Builder $query): Builder
    {
        $today = Carbon::today()->toDateString();

        return $query->where('valid_from', '<=', $today)
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today));
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

    public function isCurrentlyVigente(): bool
    {
        return $this->is_active && $this->validityStatus() === PriceListValidityStatus::Vigente;
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
