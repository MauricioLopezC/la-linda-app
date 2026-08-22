<?php

namespace App\Models\Pricing;

use App\Concerns\NormalizesUniqueAttributes;
use Database\Factories\Pricing\VatRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $description
 * @property string $description_normalized
 * @property float $percentage
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['description', 'percentage', 'is_active'])]
class VatRate extends Model
{
    /** @use HasFactory<VatRateFactory> */
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
            'percentage' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<VatRate>  $query
     * @return Builder<VatRate>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isInUse(): bool
    {
        // TODO: check Article/PriceList/Sale once each relation is (re)implemented.
        return false;
    }

    /** @return array<string, string> */
    protected function uniqueAttributesToNormalize(): array
    {
        return [
            'description' => 'description_normalized',
        ];
    }
}
