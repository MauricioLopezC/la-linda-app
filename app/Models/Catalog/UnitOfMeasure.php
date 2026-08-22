<?php

namespace App\Models\Catalog;

use App\Concerns\NormalizesUniqueAttributes;
use Database\Factories\Catalog\UnitOfMeasureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $name_normalized
 * @property string $abbreviation
 * @property string $abbreviation_normalized
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'abbreviation', 'is_active'])]
class UnitOfMeasure extends Model
{
    /** @use HasFactory<UnitOfMeasureFactory> */
    use HasFactory;

    use NormalizesUniqueAttributes;

    protected $table = 'units_of_measure';

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<UnitOfMeasure>  $query
     * @return Builder<UnitOfMeasure>
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

    public function hasArticles(): bool
    {
        return $this->articles()->exists();
    }

    /** @return array<string, string> */
    protected function uniqueAttributesToNormalize(): array
    {
        return [
            'name' => 'name_normalized',
            'abbreviation' => 'abbreviation_normalized',
        ];
    }
}
