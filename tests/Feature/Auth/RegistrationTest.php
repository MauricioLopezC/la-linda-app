<?php

use App\Enums\Security\UserRole;
use App\Models\Customers\Customer;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register as clients', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test Client',
        'email' => 'client@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'phone' => '1122334455',
        'address' => 'Av. San Martín 123',
        'id_number' => '40123456',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('tienda.home', absolute: false));

    /** @var User $user */
    $user = auth()->user();
    expect($user->role)->toBe(UserRole::Cliente)
        ->and($user->customer_id)->not->toBeNull()
        ->and($user->isClient())->toBeTrue();

    $customer = Customer::find($user->customer_id);
    expect($customer)->not->toBeNull()
        ->and($customer->name)->toBe('Test Client')
        ->and($customer->phone)->toBe('1122334455')
        ->and($customer->address)->toBe('Av. San Martín 123')
        ->and($customer->id_number)->toBe('40123456');
});
