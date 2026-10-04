<?php

use App\Enums\Customers\CustomerTaxCondition;
use App\Enums\Security\UserRole;
use App\Models\Customers\Customer;
use App\Models\User;

test('guest can view the online store home', function () {
    $response = $this->get(route('tienda.home'));

    $response->assertOk();
});

test('client can log in and is redirected to the online store home', function () {
    $client = User::factory()->client()->create();

    $response = $this->post(route('login.store'), [
        'email' => $client->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($client);
    $response->assertRedirect(route('tienda.home', absolute: false));
});

test('internal staff logs in and is redirected to the dashboard', function () {
    $staff = User::factory()->internal()->create();

    $response = $this->post(route('login.store'), [
        'email' => $staff->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($staff);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('new client can register with optional contact details', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Lucía Gómez',
        'email' => 'lucia@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'phone' => '11-4455-6677',
        'address' => 'Av. Rivadavia 4567',
        'id_number' => '38999888',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('tienda.home', absolute: false));

    /** @var User $user */
    $user = auth()->user();
    expect($user->name)->toBe('Lucía Gómez')
        ->and($user->email)->toBe('lucia@example.com')
        ->and($user->role)->toBe(UserRole::Cliente)
        ->and($user->isClient())->toBeTrue();

    $customer = $user->customer;
    expect($customer)->not->toBeNull()
        ->and($customer->name)->toBe('Lucía Gómez')
        ->and($customer->phone)->toBe('11-4455-6677')
        ->and($customer->address)->toBe('Av. Rivadavia 4567')
        ->and($customer->id_number)->toBe('38999888')
        ->and($customer->tax_condition)->toBe(CustomerTaxCondition::ConsumidorFinal);
});

test('registration rejects duplicate email', function () {
    User::factory()->create(['email' => 'existente@example.com']);

    $response = $this->post(route('register.store'), [
        'name' => 'Otro Cliente',
        'email' => 'existente@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors(['email']);
    $this->assertGuest();
});

test('registration rejects duplicate dni if already registered to another customer', function () {
    Customer::factory()->create([
        'id_number' => '30111222',
    ]);

    $response = $this->post(route('register.store'), [
        'name' => 'Nuevo Intento',
        'email' => 'nuevo@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'id_number' => '30111222',
    ]);

    $response->assertSessionHasErrors([
        'id_number' => 'El DNI ingresado ya se encuentra registrado.',
    ]);
    $this->assertGuest();
});

test('registration rejects duplicate dni if already registered to another user', function () {
    $existingCustomer = Customer::factory()->create([
        'id_number' => '30111222',
    ]);

    User::factory()->client()->create([
        'customer_id' => $existingCustomer->id,
    ]);

    $response = $this->post(route('register.store'), [
        'name' => 'Nuevo Intento',
        'email' => 'nuevo@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'id_number' => '30111222',
    ]);

    $response->assertSessionHasErrors([
        'id_number' => 'El DNI ingresado ya se encuentra registrado.',
    ]);
    $this->assertGuest();
});

test('registration accepts formatted dni with dots and normalizes it', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Cliente Con Puntos',
        'email' => 'conpuntos@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'id_number' => '38.999.888',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('tienda.home', absolute: false));

    /** @var User $user */
    $user = auth()->user();
    expect($user->customer)->not->toBeNull()
        ->and($user->customer->id_number)->toBe('38999888');
});

test('client is forbidden from accessing the internal dashboard', function () {
    $client = User::factory()->client()->create();

    $response = $this->actingAs($client)->get(route('dashboard'));

    $response->assertRedirect(route('tienda.home'));
    $response->assertSessionMissing('error');
});

test('client is forbidden from accessing internal purchasing modules', function () {
    $client = User::factory()->client()->create();

    $response = $this->actingAs($client)->get(route('purchasing.suppliers.index'));

    $response->assertRedirect(route('tienda.home'));
    $response->assertSessionMissing('error');
});

test('client json request to internal routes returns 403 forbidden', function () {
    $client = User::factory()->client()->create();

    $response = $this->actingAs($client)->getJson(route('purchasing.suppliers.index'));

    $response->assertForbidden();
});

test('internal staff can access internal routes without restriction', function () {
    $staff = User::factory()->internal()->create();

    $response = $this->actingAs($staff)->get(route('dashboard'));

    $response->assertOk();
});

test('client can view their account page', function () {
    $customer = Customer::factory()->create([
        'name' => 'Carlos Cliente',
        'phone' => '11-9988-7766',
        'address' => 'Mitre 123',
        'id_number' => '25123456',
    ]);

    $client = User::factory()->client()->create([
        'name' => 'Carlos Cliente',
        'customer_id' => $customer->id,
    ]);

    $response = $this->actingAs($client)->get(route('tienda.account.show'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('ecommerce/account/show')
        ->has('customer', fn ($prop) => $prop
            ->where('name', 'Carlos Cliente')
            ->where('phone', '11-9988-7766')
            ->where('address', 'Mitre 123')
            ->where('id_number', '25123456')
            ->etc()
        )
    );
});

test('client can update their contact information', function () {
    $customer = Customer::factory()->create([
        'name' => 'Viejo Nombre',
        'phone' => '+54 9 11 1111-1111',
        'address' => 'Calle Vieja 1',
        'id_number' => '20123456',
    ]);

    $client = User::factory()->client()->create([
        'name' => 'Viejo Nombre',
        'customer_id' => $customer->id,
    ]);

    $response = $this->actingAs($client)->put(route('tienda.account.update'), [
        'name' => 'Nuevo Nombre',
        'phone' => '+54 9 11 2222-2222',
        'address' => 'Calle Nueva 2',
    ]);

    $response->assertRedirect(route('tienda.account.show'));
    $response->assertSessionHas('success');

    $client->refresh();
    $customer->refresh();

    expect($client->name)->toBe('Nuevo Nombre')
        ->and($customer->name)->toBe('Nuevo Nombre')
        ->and($customer->phone)->toBe('+54 9 11 2222-2222')
        ->and($customer->address)->toBe('Calle Nueva 2')
        ->and($customer->id_number)->toBe('20123456');
});

test('customer dni is immutable on account update', function () {
    $customer = Customer::factory()->create([
        'name' => 'Cliente Fiel',
        'id_number' => '11111111',
    ]);
    $client = User::factory()->client()->create([
        'customer_id' => $customer->id,
    ]);

    $response = $this->actingAs($client)->put(route('tienda.account.update'), [
        'name' => 'Cliente Modificado',
        'id_number' => '99999999', // attempt to change DNI
    ]);

    $response->assertRedirect(route('tienda.account.show'));

    $customer->refresh();
    expect($customer->id_number)->toBe('11111111')
        ->and($customer->name)->toBe('Cliente Modificado');
});

test('guest cannot access account page', function () {
    $response = $this->get(route('tienda.account.show'));

    $response->assertRedirect(route('login'));
});

test('legacy store auth routes redirect to unified auth routes', function () {
    $this->get('/tienda/login')->assertRedirect('/login');
    $this->get('/tienda/registro')->assertRedirect('/register');
});

test('registration rejects non-numeric or invalid length dni', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Dni Invalido',
        'email' => 'invalido@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'id_number' => 'ABC1234',
    ]);

    $response->assertSessionHasErrors(['id_number']);

    $responseShort = $this->post(route('register.store'), [
        'name' => 'Dni Corto',
        'email' => 'corto@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'id_number' => '12345',
    ]);

    $responseShort->assertSessionHasErrors(['id_number']);
});

test('registration validates phone format against argentinian standard', function () {
    $responseInvalid = $this->post(route('register.store'), [
        'name' => 'Telefono Invalido',
        'email' => 'tel.invalido@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'phone' => 'telefono-invalido-123',
    ]);

    $responseInvalid->assertSessionHasErrors(['phone']);

    $responseValid = $this->post(route('register.store'), [
        'name' => 'Telefono Valido',
        'email' => 'tel.valido@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'phone' => '+54 9 387 1234567',
    ]);

    $responseValid->assertSessionDoesntHaveErrors(['phone']);
    $responseValid->assertRedirect(route('tienda.home', absolute: false));
});

test('account update validates phone format', function () {
    $customer = Customer::factory()->create([
        'phone' => '+54 9 387 1234567',
    ]);
    $client = User::factory()->client()->create([
        'customer_id' => $customer->id,
    ]);

    $response = $this->actingAs($client)->put(route('tienda.account.update'), [
        'name' => 'Nombre Valido',
        'phone' => 'numero_invalido',
    ]);

    $response->assertSessionHasErrors(['phone']);
});

test('client is redirected directly to store home without error banner when accessing internal routes', function () {
    $client = User::factory()->client()->create();

    $response = $this->actingAs($client)
        ->from(route('tienda.home'))
        ->get(route('dashboard'));

    $response->assertRedirect(route('tienda.home'));
    $response->assertSessionMissing('error');

    $followResponse = $this->actingAs($client)->get(route('tienda.home'));
    $followResponse->assertOk();
    $followResponse->assertInertia(fn ($page) => $page
        ->component('ecommerce/index')
        ->where('flash.error', null)
    );
});

test('guest visiting store home does not receive cashSession data', function () {
    $response = $this->get(route('tienda.home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('ecommerce/index')
        ->where('auth.user', null)
        ->where('cashSession', null)
    );
});

test('client visiting store home does not receive cashSession data', function () {
    $client = User::factory()->client()->create();

    $response = $this->actingAs($client)->get(route('tienda.home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('ecommerce/index')
        ->where('cashSession', null)
    );
});
