<?php

namespace Database\Seeders\Customers;

use App\Enums\Customers\CustomerIdType;
use App\Enums\Customers\CustomerTaxCondition;
use App\Enums\Customers\PersonType;
use App\Models\Customers\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Cliente inmutable por defecto "Consumidor Final"
        Customer::firstOrCreate(
            ['is_default' => true],
            [
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
            ]
        );

        // 2. Clientes demostrativos iniciales
        $demoCustomers = [
            [
                'person_type' => PersonType::Juridica,
                'name' => 'Distribuidora del Norte S.A.',
                'id_type' => CustomerIdType::Cuit,
                'id_number' => '30500858628',
                'tax_condition' => CustomerTaxCondition::ResponsibleInscripto,
                'address' => 'Av. Independencia 1250, Salta',
                'phone' => '+54 387 431-2200',
                'email' => 'ventas@distribuidoranorte.com.ar',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'person_type' => PersonType::Fisica,
                'name' => 'Gómez Martín Eduardo',
                'id_type' => CustomerIdType::Cuit,
                'id_number' => '20289456121',
                'tax_condition' => CustomerTaxCondition::Monotributo,
                'address' => 'Calle San Martín 450, Salta',
                'phone' => '+54 387 15-456-7890',
                'email' => 'martin.gomez@gmail.com',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'person_type' => PersonType::Fisica,
                'name' => 'López Florencia Belén',
                'id_type' => CustomerIdType::Dni,
                'id_number' => '35123456',
                'tax_condition' => CustomerTaxCondition::ConsumidorFinal,
                'address' => 'Pje. Los Álamos 88, Salta',
                'phone' => '+54 387 15-612-3456',
                'email' => 'flor.lopez@hotmail.com',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'person_type' => PersonType::Juridica,
                'name' => 'Fundación Hospitalaria San Bernardo',
                'id_type' => CustomerIdType::Cuit,
                'id_number' => '30502793175',
                'tax_condition' => CustomerTaxCondition::Exento,
                'address' => 'Mariano Boedo 120, Salta',
                'phone' => '+54 387 421-5500',
                'email' => 'administracion@fundacionsanbernardo.org.ar',
                'is_active' => true,
                'is_default' => false,
            ],
        ];

        foreach ($demoCustomers as $customerData) {
            Customer::firstOrCreate(
                ['id_number' => $customerData['id_number']],
                $customerData
            );
        }
    }
}
