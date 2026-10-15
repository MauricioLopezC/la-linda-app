<?php

use App\Enums\Security\UserRole;
use App\Models\User;

dataset('role homes', [
    'administrador' => [UserRole::Administrador, 'dashboard'],
    'gerente' => [UserRole::Gerente, 'dashboard'],
    'compras' => [UserRole::Compras, 'purchasing.orders.index'],
    'deposito' => [UserRole::Deposito, 'inventory.stocks.index'],
    'cajero' => [UserRole::Cajero, 'sales.cash-sessions.current'],
    'cliente' => [UserRole::Cliente, 'tienda.home'],
]);

test('each role lands on its main screen after logging in', function (UserRole $role, string $home) {
    $user = User::factory()->role($role)->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route($home, absolute: false));
})->with('role homes');

test('an authenticated user visiting a guest page is sent to their main screen', function (UserRole $role, string $home) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)->get(route('login'))->assertRedirect(route($home));
})->with('role homes');
