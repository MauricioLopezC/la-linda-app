<?php

namespace Database\Factories\Catalog;

use App\Enums\Catalog\ArticleStatus;
use App\Models\Catalog\Article;
use App\Models\Catalog\Category;
use App\Models\Catalog\UnitOfMeasure;
use App\Models\Pricing\VatRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'description' => fake()->unique()->words(3, true),
            'internal_code' => fake()->unique()->bothify('ART-####'),
            'barcode' => null,
            'category_id' => Category::factory(),
            'brand_id' => null,
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'vat_rate_id' => VatRate::factory(),
            'status' => ArticleStatus::Active,
            'is_online_publishable' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => ArticleStatus::Inactive,
        ]);
    }

    public function discontinued(): static
    {
        return $this->state(fn (): array => [
            'status' => ArticleStatus::Discontinued,
        ]);
    }
}
