<?php

use App\Enums\Customers\CustomerIdType;
use App\Enums\Customers\CustomerTaxCondition;
use App\Enums\Customers\PersonType;
use App\Models\Customers\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guest cannot access customer management', function () {
    $this->get(route('customers.index'))->assertRedirect(route('login'));
});

test('user can view customers list with default customer and filter by search, condition and status', function () {
    $user = User::factory()->create();

    $default = Customer::factory()->defaultCustomer()->create();

    $ri = Customer::factory()->create([
        'person_type' => PersonType::Juridica,
        'name' => 'Distribuidora del Norte S.A.',
        'id_type' => CustomerIdType::Cuit,
        'id_number' => '30500858628',
        'tax_condition' => CustomerTaxCondition::ResponsibleInscripto,
        'email' => 'norte@distribuidora.com',
        'is_active' => true,
    ]);

    $mono = Customer::factory()->create([
        'person_type' => PersonType::Fisica,
        'name' => 'Martín Gómez',
        'id_type' => CustomerIdType::Cuit,
        'id_number' => '20289456121',
        'tax_condition' => CustomerTaxCondition::Monotributo,
        'email' => 'martin@gmail.com',
        'is_active' => false,
    ]);

    // List all (default customer is pinned at top)
    $this->actingAs($user)->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/index')
            ->has('customers', 3)
            ->has('taxConditions')
            ->has('personTypes')
            ->has('idTypes')
            ->where('customers.0.is_default', true)
        );

    // Filter by search name
    $this->actingAs($user)->get(route('customers.index', ['search' => 'Distribuidora']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/index')
            ->has('customers', 1)
            ->where('customers.0.name', 'Distribuidora del Norte S.A.')
        );

    // Filter by search CUIT with formatting
    $this->actingAs($user)->get(route('customers.index', ['search' => '30-50085862-8']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/index')
            ->has('customers', 1)
            ->where('customers.0.name', 'Distribuidora del Norte S.A.')
        );

    // Filter by tax condition
    $this->actingAs($user)->get(route('customers.index', ['tax_condition' => CustomerTaxCondition::Monotributo->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/index')
            ->has('customers', 1)
            ->where('customers.0.name', 'Martín Gómez')
        );

    // Filter by status active
    $this->actingAs($user)->get(route('customers.index', ['status' => 'active']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/index')
            ->has('customers', 2)
        );
});

test('user can register a Responsable Inscripto with valid CUIT', function () {
    $user = User::factory()->create();

    $payload = [
        'person_type' => PersonType::Juridica->value,
        'name' => 'Cervecería Salteña S.A.',
        'tax_condition' => CustomerTaxCondition::ResponsibleInscripto->value,
        'id_type' => CustomerIdType::Cuit->value,
        'id_number' => '30-50279317-5',
        'address' => 'Av. Belgrano 1000',
        'phone' => '+54 387 400-0000',
        'email' => 'contacto@cerveceriasaltena.com',
        'is_active' => true,
    ];

    $this->actingAs($user)
        ->post(route('customers.store'), $payload)
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('customers', [
        'name' => 'Cervecería Salteña S.A.',
        'name_normalized' => 'cervecería salteña s.a.',
        'id_type' => 'cuit',
        'id_number' => '30502793175',
        'tax_condition' => 'responsable_inscripto',
        'is_default' => false,
    ]);
});

test('validations enforce CUIT with modulo 11 for Responsables Inscriptos and Personas Jurídicas', function () {
    $user = User::factory()->create();

    // Sin CUIT
    $this->actingAs($user)
        ->post(route('customers.store'), [
            'person_type' => PersonType::Juridica->value,
            'name' => 'Empresa Test',
            'tax_condition' => CustomerTaxCondition::ResponsibleInscripto->value,
            'id_type' => CustomerIdType::Cuit->value,
            'id_number' => '',
        ])
        ->assertSessionHasErrors(['id_number']);

    // CUIT con dígito verificador inválido
    $this->actingAs($user)
        ->post(route('customers.store'), [
            'person_type' => PersonType::Juridica->value,
            'name' => 'Empresa Test',
            'tax_condition' => CustomerTaxCondition::ResponsibleInscripto->value,
            'id_type' => CustomerIdType::Cuit->value,
            'id_number' => '30-50085862-0', // Último dígito debería ser 8
        ])
        ->assertSessionHasErrors(['id_number']);

    // Responsable Inscripto con tipo DNI (debe forzar CUIT)
    $this->actingAs($user)
        ->post(route('customers.store'), [
            'person_type' => PersonType::Fisica->value,
            'name' => 'Persona RI Test',
            'tax_condition' => CustomerTaxCondition::ResponsibleInscripto->value,
            'id_type' => CustomerIdType::Dni->value,
            'id_number' => '35123456',
        ])
        ->assertSessionHasErrors(['id_type']);
});

test('id_number must be unique across customers', function () {
    $user = User::factory()->create();

    Customer::factory()->create([
        'id_type' => CustomerIdType::Cuit,
        'id_number' => '30500858628',
    ]);

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'person_type' => PersonType::Juridica->value,
            'name' => 'Otra Empresa S.A.',
            'tax_condition' => CustomerTaxCondition::ResponsibleInscripto->value,
            'id_type' => CustomerIdType::Cuit->value,
            'id_number' => '30-50085862-8',
        ])
        ->assertSessionHasErrors(['id_number']);
});

test('user can register Consumidor Final without document or with DNI', function () {
    $user = User::factory()->create();

    // Consumidor Final sin documento
    $this->actingAs($user)
        ->post(route('customers.store'), [
            'person_type' => PersonType::Fisica->value,
            'name' => 'Cliente Ocasional',
            'tax_condition' => CustomerTaxCondition::ConsumidorFinal->value,
            'id_type' => CustomerIdType::SinIdentificar->value,
            'id_number' => '',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('customers', [
        'name' => 'Cliente Ocasional',
        'id_type' => 'sin_identificar',
        'id_number' => null,
        'tax_condition' => 'consumidor_final',
    ]);

    // Consumidor Final con DNI válido
    $this->actingAs($user)
        ->post(route('customers.store'), [
            'person_type' => PersonType::Fisica->value,
            'name' => 'Florencia López',
            'tax_condition' => CustomerTaxCondition::ConsumidorFinal->value,
            'id_type' => CustomerIdType::Dni->value,
            'id_number' => '35123456',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('customers', [
        'name' => 'Florencia López',
        'id_type' => 'dni',
        'id_number' => '35123456',
    ]);
});

test('email format is validated if provided', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'person_type' => PersonType::Fisica->value,
            'name' => 'Test Email',
            'tax_condition' => CustomerTaxCondition::ConsumidorFinal->value,
            'id_type' => CustomerIdType::SinIdentificar->value,
            'email' => 'correo-invalido',
        ])
        ->assertSessionHasErrors(['email']);
});

test('user can update a regular customer', function () {
    $user = User::factory()->create();

    $customer = Customer::factory()->create([
        'person_type' => PersonType::Fisica,
        'name' => 'Juan Original',
        'tax_condition' => CustomerTaxCondition::ConsumidorFinal,
        'id_type' => CustomerIdType::Dni,
        'id_number' => '35123456',
    ]);

    $this->actingAs($user)
        ->put(route('customers.update', $customer), [
            'person_type' => PersonType::Fisica->value,
            'name' => 'Juan Modificado',
            'tax_condition' => CustomerTaxCondition::ConsumidorFinal->value,
            'id_type' => CustomerIdType::Dni->value,
            'id_number' => '35123456', // Mismo DNI
            'address' => 'Nueva Dirección 123',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($customer->fresh()->name)->toBe('Juan Modificado')
        ->and($customer->fresh()->address)->toBe('Nueva Dirección 123');
});

test('default Consumidor Final is protected against modification', function () {
    $user = User::factory()->create();

    $defaultCustomer = Customer::factory()->defaultCustomer()->create();

    $this->actingAs($user)
        ->put(route('customers.update', $defaultCustomer), [
            'person_type' => PersonType::Fisica->value,
            'name' => 'Consumidor Final Cambiado',
            'tax_condition' => CustomerTaxCondition::ConsumidorFinal->value,
            'id_type' => CustomerIdType::SinIdentificar->value,
            'id_number' => '',
        ])
        ->assertSessionHasErrors(['customer']);

    expect($defaultCustomer->fresh()->name)->toBe('Consumidor Final');
});

test('default Consumidor Final is protected against deactivation', function () {
    $user = User::factory()->create();

    $defaultCustomer = Customer::factory()->defaultCustomer()->create();

    $this->actingAs($user)
        ->patch(route('customers.toggle', $defaultCustomer))
        ->assertSessionHasErrors(['customer']);

    expect($defaultCustomer->fresh()->is_active)->toBeTrue();
});

test('default Consumidor Final is protected against deletion', function () {
    $user = User::factory()->create();

    $defaultCustomer = Customer::factory()->defaultCustomer()->create();

    $this->actingAs($user)
        ->delete(route('customers.destroy', $defaultCustomer))
        ->assertSessionHasErrors(['customer']);

    $this->assertDatabaseHas('customers', [
        'id' => $defaultCustomer->id,
    ]);
});

test('regular customer can be deleted physically if has no associated records', function () {
    $user = User::factory()->create();

    $customer = Customer::factory()->create();

    $this->actingAs($user)
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('customers', [
        'id' => $customer->id,
    ]);
});

test('user can toggle active status of regular customer', function () {
    $user = User::factory()->create();

    $customer = Customer::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->patch(route('customers.toggle', $customer))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($customer->fresh()->is_active)->toBeFalse();
});

test('valid phone formats are accepted for Argentina standard', function (?string $phone) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'person_type' => PersonType::Fisica->value,
            'name' => 'Test Phone',
            'tax_condition' => CustomerTaxCondition::ConsumidorFinal->value,
            'id_type' => CustomerIdType::SinIdentificar->value,
            'phone' => $phone,
        ])
        ->assertSessionHasNoErrors(['phone']);
})->with([
    'null' => [null],
    'full international' => ['+54 9 387 1234567'],
    'international 0054' => ['0054 9 387 1234567'],
    'with dashes' => ['387 15-123-4567'],
    'with dots' => ['11.1234.5678'],
    'only numbers' => ['3874000000'],
]);

test('invalid phone formats are rejected for Argentina standard', function (string $phone) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'person_type' => PersonType::Fisica->value,
            'name' => 'Test Phone',
            'tax_condition' => CustomerTaxCondition::ConsumidorFinal->value,
            'id_type' => CustomerIdType::SinIdentificar->value,
            'phone' => $phone,
        ])
        ->assertSessionHasErrors(['phone']);
})->with([
    'other country' => ['+55 9 387 1234567'],
    'letters' => ['+54 9 abc 1234567'],
    'too short' => ['123'],
]);
