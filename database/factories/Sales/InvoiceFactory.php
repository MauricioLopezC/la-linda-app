<?php

namespace Database\Factories\Sales;

use App\Enums\Sales\InvoiceType;
use App\Models\Sales\Invoice;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * Define the model's default state: the invoice of a confirmed sale, with the customer
     * and session taken from that sale.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory()->confirmed()->state(['total_amount' => '1210.00']),
            'cash_session_id' => fn (array $attributes): ?int => Sale::find((int) $attributes['sale_id'])?->cash_session_id,
            'point_of_sale_id' => fn (array $attributes): ?int => Sale::find((int) $attributes['sale_id'])?->point_of_sale_id,
            'point_of_sale_number' => fn (array $attributes): int => PointOfSale::findOrFail((int) $attributes['point_of_sale_id'])->number,
            'type' => InvoiceType::B,
            'number' => fn (array $attributes): int => (int) Invoice::query()
                ->where('point_of_sale_id', $attributes['point_of_sale_id'])
                ->where('type', $attributes['type'])
                ->max('number') + 1,
            'issued_at' => now(),
            'customer_id' => fn (array $attributes): ?int => Sale::find((int) $attributes['sale_id'])?->customer_id,
            'customer_name' => fake()->name(),
            'customer_tax_condition' => 'consumidor_final',
            'customer_id_type' => null,
            'customer_id_number' => null,
            'customer_address' => null,
            'net_amount' => '1000.00',
            'vat_amount' => '210.00',
            'total_amount' => '1210.00',
            'user_id' => fn (array $attributes): ?int => Sale::find((int) $attributes['sale_id'])?->user_id,
        ];
    }

    public function typeA(): static
    {
        return $this->state(fn (): array => [
            'type' => InvoiceType::A,
            'customer_tax_condition' => 'responsable_inscripto',
        ]);
    }
}
