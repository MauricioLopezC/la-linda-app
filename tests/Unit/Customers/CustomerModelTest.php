<?php

use App\Enums\Customers\CustomerIdType;
use App\Enums\Customers\CustomerTaxCondition;
use App\Enums\Customers\PersonType;
use App\Models\Customers\Customer;
use Database\Seeders\Customers\CustomerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('customer normalizes name to name_normalized upon saving', function () {
    $customer = Customer::create([
        'person_type' => PersonType::Fisica,
        'name' => '  Juan Pérez González  ',
        'id_type' => CustomerIdType::SinIdentificar,
        'id_number' => null,
        'tax_condition' => CustomerTaxCondition::ConsumidorFinal,
    ]);

    expect($customer->name)->toBe('Juan Pérez González')
        ->and($customer->name_normalized)->toBe('juan pérez gonzález');
});

test('customer casts enums and booleans correctly', function () {
    $customer = Customer::factory()->create([
        'person_type' => PersonType::Juridica,
        'id_type' => CustomerIdType::Cuit,
        'tax_condition' => CustomerTaxCondition::ResponsibleInscripto,
        'is_active' => true,
        'is_default' => false,
    ]);

    expect($customer->person_type)->toBe(PersonType::Juridica)
        ->and($customer->id_type)->toBe(CustomerIdType::Cuit)
        ->and($customer->tax_condition)->toBe(CustomerTaxCondition::ResponsibleInscripto)
        ->and($customer->is_active)->toBeTrue()
        ->and($customer->is_default)->toBeFalse()
        ->and($customer->isProtected())->toBeFalse();
});

test('formattedIdNumber formats CUIT with dashes and leaves DNI intact', function () {
    $cuitCustomer = Customer::factory()->create([
        'id_type' => CustomerIdType::Cuit,
        'id_number' => '30500858628',
    ]);

    $dniCustomer = Customer::factory()->create([
        'id_type' => CustomerIdType::Dni,
        'id_number' => '35123456',
    ]);

    $unidentifiedCustomer = Customer::factory()->create([
        'id_type' => CustomerIdType::SinIdentificar,
        'id_number' => null,
    ]);

    expect($cuitCustomer->formattedIdNumber())->toBe('30-50085862-8')
        ->and($dniCustomer->formattedIdNumber())->toBe('35123456')
        ->and($unidentifiedCustomer->formattedIdNumber())->toBeNull();
});

test('seeder creates immutable default Consumidor Final', function () {
    $this->seed(CustomerSeeder::class);

    $defaultCustomer = Customer::query()->where('is_default', true)->first();

    expect($defaultCustomer)->not->toBeNull()
        ->and($defaultCustomer->name)->toBe('Consumidor Final')
        ->and($defaultCustomer->isProtected())->toBeTrue()
        ->and($defaultCustomer->tax_condition)->toBe(CustomerTaxCondition::ConsumidorFinal)
        ->and($defaultCustomer->id_number)->toBeNull()
        ->and($defaultCustomer->is_active)->toBeTrue();
});
