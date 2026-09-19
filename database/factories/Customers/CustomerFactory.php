<?php

namespace Database\Factories\Customers;

use App\Enums\Customers\CustomerIdType;
use App\Enums\Customers\CustomerTaxCondition;
use App\Enums\Customers\PersonType;
use App\Models\Customers\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_type' => PersonType::Fisica,
            'name' => fake()->name(),
            'id_type' => CustomerIdType::Dni,
            'id_number' => (string) fake()->unique()->numerify('########'),
            'tax_condition' => CustomerTaxCondition::ConsumidorFinal,
            'address' => fake()->streetAddress(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'is_active' => true,
            'is_default' => false,
        ];
    }

    public function responsableInscripto(): static
    {
        // Generamos CUIT válido con prefijo 30
        $validCuits = [
            '30500858628',
            '30502793175',
            '30500511849',
            '30500949461',
            '20289456121',
        ];

        return $this->state(fn (array $attributes) => [
            'person_type' => PersonType::Juridica,
            'name' => fake()->company(),
            'id_type' => CustomerIdType::Cuit,
            'id_number' => fake()->unique()->randomElement($validCuits),
            'tax_condition' => CustomerTaxCondition::ResponsibleInscripto,
        ]);
    }

    public function consumidorFinal(): static
    {
        return $this->state(fn (array $attributes) => [
            'person_type' => PersonType::Fisica,
            'id_type' => CustomerIdType::SinIdentificar,
            'id_number' => null,
            'tax_condition' => CustomerTaxCondition::ConsumidorFinal,
        ]);
    }

    public function defaultCustomer(): static
    {
        return $this->state(fn (array $attributes) => [
            'person_type' => PersonType::Fisica,
            'name' => 'Consumidor Final',
            'id_type' => CustomerIdType::SinIdentificar,
            'id_number' => null,
            'tax_condition' => CustomerTaxCondition::ConsumidorFinal,
            'address' => null,
            'phone' => null,
            'email' => null,
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    public function monotributo(): static
    {
        return $this->state(fn (array $attributes) => [
            'person_type' => PersonType::Fisica,
            'name' => fake()->name(),
            'id_type' => CustomerIdType::Cuit,
            'id_number' => '20289456121',
            'tax_condition' => CustomerTaxCondition::Monotributo,
        ]);
    }
}
