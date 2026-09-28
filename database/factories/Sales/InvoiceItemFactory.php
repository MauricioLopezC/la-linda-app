<?php

namespace Database\Factories\Sales;

use App\Models\Catalog\Article;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'article_id' => Article::factory(),
            'description' => fake()->words(3, true),
            'quantity' => '1.000',
            'unit_price' => '1210.00',
            'vat_rate' => '21.00',
            'net_amount' => '1000.00',
            'vat_amount' => '210.00',
            'line_total' => '1210.00',
        ];
    }
}
