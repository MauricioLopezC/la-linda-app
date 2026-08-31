<?php

namespace Database\Factories\Purchasing;

use App\Enums\Purchasing\SupplierTaxCondition;
use App\Models\Purchasing\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_name' => fake()->company().' '.$this->faker->randomElement(['S.A.', 'S.R.L.', 'S.A.I.C.']),
            'tax_id' => $this->generateValidCuit(),
            'tax_condition' => $this->faker->randomElement([
                SupplierTaxCondition::ResponsibleInscripto,
                SupplierTaxCondition::Monotributo,
                SupplierTaxCondition::Exento,
            ]),
            'address' => fake()->streetAddress().', '.fake()->city(),
            'rubro' => $this->faker->randomElement(['Alimentos secos', 'Lácteos', 'Bebidas', 'Limpieza', 'Golosinas', 'Fiambres', 'Panadería']),
            'bank_account' => 'CBU: 01700'.fake()->numerify('################').' / Alias: '.strtoupper(fake()->word()).'.PAGOS',
            'commercial_terms' => $this->faker->randomElement([
                'Pago a 30 días fecha factura.',
                'Pago a 15 días con 5% de descuento por pronto pago.',
                'Pago a 60 días fecha factura. Entrega en depósito central.',
                'Transferencia contra entrega de mercadería.',
            ]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    /**
     * Generate a valid 11-digit Argentine CUIT.
     */
    private function generateValidCuit(): string
    {
        $prefix = (string) $this->faker->randomElement(['30', '33', '20', '27']);
        $middle = (string) fake()->numerify('########');
        $partial = $prefix.$middle;

        $multipliers = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int) $partial[$i] * $multipliers[$i];
        }

        $mod = $sum % 11;
        $dv = match ($mod) {
            0 => 0,
            1 => 9,
            default => 11 - $mod,
        };

        return $partial.$dv;
    }
}
