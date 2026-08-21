<?php

namespace App\Models\Pricing;

use App\Concerns\NormalizesUniqueAttributes;
use App\Models\Catalog\Article;
use Database\Factories\Pricing\VatRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /** @return HasMany<Article, $this> */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function isInUse(): bool
    {
        // TODO: also check PriceList/Sale once pricing/invoicing is implemented.
        return $this->articles()->exists();
    }

    /** @return array<string, string> */
    protected function uniqueAttributesToNormalize(): array
    {
        return [
            'description' => 'description_normalized',
        ];
    }
}
