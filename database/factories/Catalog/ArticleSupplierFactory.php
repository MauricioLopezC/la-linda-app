<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\Article;
use App\Models\Catalog\ArticleSupplier;
use App\Models\Purchasing\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleSupplier>
 */
class ArticleSupplierFactory extends Factory
{
    protected $model = ArticleSupplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'supplier_id' => Supplier::factory(),
            'supplier_article_code' => 'PROV-'.fake()->unique()->numerify('#####'),
            'last_cost' => fake()->randomFloat(2, 10, 500),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
