<?php

use App\Enums\Security\Permission;
use App\Enums\Security\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Expected Gates per role (HU-004). Kept as an explicit list so any change to the
 * matrix in UserRole::permissions() has to be acknowledged here.
 *
 * @return array<string, array{UserRole, list<Permission>}>
 */
dataset('role permissions', fn (): array => [
    'administrador' => [UserRole::Administrador, Permission::cases()],
    'gerente' => [UserRole::Gerente, [
        Permission::ViewDashboard,
        Permission::ManageWebOrders,
        Permission::ManageCatalog,
        Permission::ManagePricing,
        Permission::ManagePurchases,
        Permission::ManageStock,
        Permission::ManageCustomers,
        Permission::ManageOrganization,
    ]],
    'compras' => [UserRole::Compras, [Permission::ManageCatalog, Permission::ManagePurchases, Permission::ManageStock]],
    'deposito' => [UserRole::Deposito, [Permission::ManageStock, Permission::ManageWebOrders]],
    'cajero' => [UserRole::Cajero, [Permission::OperateCash, Permission::ManageCustomers]],
    'cliente' => [UserRole::Cliente, []],
]);

test('each role is granted exactly its module gates', function (UserRole $role, array $granted) {
    $user = User::factory()->role($role)->create();

    foreach (Permission::cases() as $permission) {
        expect(Gate::forUser($user)->allows($permission->value))
            ->toBe(in_array($permission, $granted, true), "{$role->value} → {$permission->value}");
    }
})->with('role permissions');

test('only the administrador can manage users', function () {
    expect(Gate::forUser(User::factory()->administrador()->create())->allows('manage-users'))->toBeTrue();

    foreach ([UserRole::Gerente, UserRole::Compras, UserRole::Deposito, UserRole::Cajero, UserRole::Cliente] as $role) {
        expect(Gate::forUser(User::factory()->role($role)->create())->allows('manage-users'))->toBeFalse();
    }
});

test('the administrador passes gates that are not part of the module catalog', function () {
    Gate::define('some-future-gate', fn (User $user): bool => false);

    expect(Gate::forUser(User::factory()->administrador()->create())->allows('some-future-gate'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->gerente()->create())->allows('some-future-gate'))->toBeFalse();
});

test('factory creates administradores by default and offers a state per role', function () {
    expect(User::factory()->create()->role)->toBe(UserRole::Administrador)
        ->and(User::factory()->gerente()->create()->role)->toBe(UserRole::Gerente)
        ->and(User::factory()->compras()->create()->role)->toBe(UserRole::Compras)
        ->and(User::factory()->deposito()->create()->role)->toBe(UserRole::Deposito)
        ->and(User::factory()->cajero()->create()->role)->toBe(UserRole::Cajero)
        ->and(User::factory()->client()->create()->role)->toBe(UserRole::Cliente);
});

test('every role except cliente is internal staff', function () {
    foreach (UserRole::cases() as $role) {
        expect(User::factory()->role($role)->make()->isInternal())->toBe($role !== UserRole::Cliente);
    }
});
