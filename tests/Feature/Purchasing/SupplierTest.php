<?php

use App\Enums\Purchasing\SupplierTaxCondition;
use App\Models\Purchasing\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guest cannot access supplier management', function () {
    $this->get(route('purchasing.suppliers.index'))->assertRedirect(route('login'));
});

test('user can view suppliers list and filter by search, condition and status', function () {
    $user = User::factory()->create();

    $molinos = Supplier::factory()->create([
        'business_name' => 'Molinos Río de la Plata',
        'tax_id' => '30500858628',
        'tax_condition' => SupplierTaxCondition::ResponsibleInscripto,
        'rubro' => 'Alimentos secos',
        'is_active' => true,
    ]);

    $arcor = Supplier::factory()->create([
        'business_name' => 'Arcor S.A.I.C.',
        'tax_id' => '30502793175',
        'tax_condition' => SupplierTaxCondition::ResponsibleInscripto,
        'rubro' => 'Golosinas',
        'is_active' => true,
    ]);

    $sanCayetano = Supplier::factory()->create([
        'business_name' => 'Distribuidora San Cayetano',
        'tax_id' => '20289456121',
        'tax_condition' => SupplierTaxCondition::Monotributo,
        'rubro' => 'Limpieza',
        'is_active' => false,
    ]);

    // List all
    $this->actingAs($user)->get(route('purchasing.suppliers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/suppliers/index')
            ->has('suppliers', 3)
            ->has('taxConditions')
        );

    // Search filter
    $this->actingAs($user)->get(route('purchasing.suppliers.index', ['search' => 'Molinos']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/suppliers/index')
            ->has('suppliers', 1)
            ->where('suppliers.0.business_name', 'Molinos Río de la Plata')
        );

    // Search by CUIT
    $this->actingAs($user)->get(route('purchasing.suppliers.index', ['search' => '30-50279317-5']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/suppliers/index')
            ->has('suppliers', 1)
            ->where('suppliers.0.business_name', 'Arcor S.A.I.C.')
        );

    // Filter by tax condition
    $this->actingAs($user)->get(route('purchasing.suppliers.index', ['tax_condition' => SupplierTaxCondition::Monotributo->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/suppliers/index')
            ->has('suppliers', 1)
            ->where('suppliers.0.business_name', 'Distribuidora San Cayetano')
        );

    // Filter by active status
    $this->actingAs($user)->get(route('purchasing.suppliers.index', ['status' => 'inactive']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/suppliers/index')
            ->has('suppliers', 1)
            ->where('suppliers.0.business_name', 'Distribuidora San Cayetano')
        );
});

test('user can create a supplier with valid data and valid CUIT', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('purchasing.suppliers.store'), [
        'business_name' => '  Mastellone Hermanos S.A.  ',
        'tax_id' => '30-50051184-9',
        'tax_condition' => SupplierTaxCondition::ResponsibleInscripto->value,
        'address' => 'Encarnación 1050, General Rodríguez',
        'rubro' => 'Lácteos',
        'bank_account' => 'CBU: 0720123420000004567890 / Alias: LA.SERENISIMA',
        'commercial_terms' => 'Pago semanal a 14 días.',
        'is_active' => true,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('suppliers', [
        'business_name' => 'Mastellone Hermanos S.A.',
        'business_name_normalized' => 'mastellone hermanos s.a.',
        'tax_id' => '30500511849',
        'tax_condition' => 'responsable_inscripto',
        'rubro' => 'Lácteos',
        'commercial_terms' => 'Pago semanal a 14 días.',
        'is_active' => true,
    ]);
});

test('supplier creation requires business name, tax id and tax condition', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('purchasing.suppliers.store'), [
        'business_name' => '',
        'tax_id' => '',
        'tax_condition' => '',
    ])->assertSessionHasErrors(['business_name', 'tax_id', 'tax_condition']);
});

test('supplier creation rejects invalid CUIT checksum or format', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('purchasing.suppliers.store'), [
        'business_name' => 'Proveedor Inválido',
        'tax_id' => '30-50085862-0', // Check digit is 8, not 0
        'tax_condition' => SupplierTaxCondition::ResponsibleInscripto->value,
    ])->assertSessionHasErrors(['tax_id']);

    $this->actingAs($user)->post(route('purchasing.suppliers.store'), [
        'business_name' => 'Proveedor Prefijo Inválido',
        'tax_id' => '50-50085862-8',
        'tax_condition' => SupplierTaxCondition::ResponsibleInscripto->value,
    ])->assertSessionHasErrors(['tax_id']);
});

test('supplier creation rejects duplicate CUIT', function () {
    $user = User::factory()->create();
    Supplier::factory()->create(['tax_id' => '30500858628']);

    $this->actingAs($user)->post(route('purchasing.suppliers.store'), [
        'business_name' => 'Otro Proveedor',
        'tax_id' => '30-50085862-8',
        'tax_condition' => SupplierTaxCondition::ResponsibleInscripto->value,
    ])->assertSessionHasErrors(['tax_id']);
});

test('supplier tax condition must belong to the closed enum list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('purchasing.suppliers.store'), [
        'business_name' => 'Proveedor Test',
        'tax_id' => '30-50085862-8',
        'tax_condition' => 'condicion_inexistente',
    ])->assertSessionHasErrors(['tax_condition']);
});

test('user can update supplier details and commercial terms', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create([
        'business_name' => 'Nombre Viejo',
        'tax_id' => '30500858628',
        'commercial_terms' => 'Términos viejos',
    ]);

    $this->actingAs($user)->put(route('purchasing.suppliers.update', $supplier), [
        'business_name' => 'Nombre Actualizado',
        'tax_id' => '30-50085862-8',
        'tax_condition' => SupplierTaxCondition::ResponsibleInscripto->value,
        'address' => 'Nueva Dirección 123',
        'rubro' => 'Nuevo Rubro',
        'bank_account' => 'Alias: NUEVO.ALIAS',
        'commercial_terms' => 'Descuento 5% pronto pago.',
        'is_active' => true,
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($supplier->fresh())
        ->business_name->toBe('Nombre Actualizado')
        ->business_name_normalized->toBe('nombre actualizado')
        ->commercial_terms->toBe('Descuento 5% pronto pago.')
        ->address->toBe('Nueva Dirección 123');
});

test('user can toggle supplier status for logical deactivation and reactivation', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['is_active' => true]);

    $this->actingAs($user)->patch(route('purchasing.suppliers.toggle', $supplier))
        ->assertSessionHasNoErrors();
    expect($supplier->fresh()->is_active)->toBeFalse();

    $this->actingAs($user)->patch(route('purchasing.suppliers.toggle', $supplier))
        ->assertSessionHasNoErrors();
    expect($supplier->fresh()->is_active)->toBeTrue();
});

test('user can update CUIT when no associated records exist', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['tax_id' => '30500858628']);

    $this->actingAs($user)->put(route('purchasing.suppliers.update', $supplier), [
        'business_name' => $supplier->business_name,
        'tax_id' => '30-50279317-5', // New valid CUIT
        'tax_condition' => $supplier->tax_condition->value,
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($supplier->fresh()->tax_id)->toBe('30502793175');
});

test('user can physically delete a supplier without associated records', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();

    $this->actingAs($user)->delete(route('purchasing.suppliers.destroy', $supplier))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
});
