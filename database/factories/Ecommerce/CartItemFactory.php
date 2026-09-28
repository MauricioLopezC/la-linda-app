<?php

namespace Database\Factories\Ecommerce;

use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'article_id' => Article::factory(),
            'quantity' => '1.000',
        ];
    }
}
