<?php

use App\Enums\Security\Permission;
use App\Enums\Security\UserRole;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * One GET screen per route group of the internal panel, with the Gate that guards it (HU-004).
 *
 * @return array<string, array{string, Permission}>
 */
dataset('module screens', fn (): array => [
    'dashboard' => ['dashboard', Permission::ViewDashboard],
    'artículos' => ['catalog.articles.index', Permission::ManageCatalog],
    'categorías' => ['catalog.categories.index', Permission::ManageCatalog],
    'marcas' => ['catalog.brands.index', Permission::ManageCatalog],
    'unidades de medida' => ['catalog.units-of-measure.index', Permission::ManageCatalog],
    'sucursales' => ['organization.branches.index', Permission::ManageOrganization],
    'depósitos' => ['inventory.warehouses.index', Permission::ManageOrganization],
    'parámetros de stock' => ['inventory.parameters.index', Permission::ManageOrganization],
    'puntos de venta' => ['sales.points-of-sale.index', Permission::ManageOrganization],
    'medios de pago' => ['sales.payment-methods.index', Permission::ManageOrganization],
    'alícuotas de IVA' => ['pricing.vat-rates.index', Permission::ManageOrganization],
    'existencias' => ['inventory.stocks.index', Permission::ManageStock],
    'ajuste de stock' => ['inventory.adjustments.create', Permission::ManageStock],
    'historial de movimientos' => ['inventory.movements.index', Permission::ManageStock],
    'listas de precios' => ['pricing.price-lists.index', Permission::ManagePricing],
    'apertura de caja' => ['sales.cash-sessions.create', Permission::OperateCash],
    'ventas' => ['sales.sales.index', Permission::OperateCash],
    'proveedores' => ['purchasing.suppliers.index', Permission::ManagePurchases],
    'comprobantes de proveedor' => ['purchasing.vouchers.index', Permission::ManagePurchases],
    'órdenes de compra' => ['purchasing.orders.index', Permission::ManagePurchases],
    'órdenes de pago' => ['purchasing.payment-orders.index', Permission::ManagePurchases],
    'cuenta corriente' => ['purchasing.account-statement.index', Permission::ManagePurchases],
    'clientes' => ['customers.index', Permission::ManageCustomers],
    'pedidos web a preparar' => ['ecommerce.orders.index', Permission::ManageWebOrders],
    'usuarios' => ['settings.users.index', Permission::ManageUsers],
]);

dataset('internal roles', fn (): array => [
    'administrador' => [UserRole::Administrador],
    'gerente' => [UserRole::Gerente],
    'compras' => [UserRole::Compras],
    'deposito' => [UserRole::Deposito],
    'cajero' => [UserRole::Cajero],
]);

test('each internal role opens the modules its gates allow and gets 403 on the rest', function (UserRole $role, string $routeName, Permission $permission) {
    $user = User::factory()->role($role)->create();

    $response = $this->actingAs($user)->get(route($routeName));

    if ($role === UserRole::Administrador || $role->grants($permission)) {
        $response->assertSuccessful();
    } else {
        $response->assertForbidden();
    }
})->with('internal roles')->with('module screens');

test('a cliente is sent back to the online store from any internal module', function (string $routeName) {
    $user = User::factory()->client()->create();

    $this->actingAs($user)->get(route($routeName))->assertRedirect(route('tienda.home'));
})->with('module screens');

test('every internal panel route is guarded by a module gate', function () {
    $ungated = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => in_array('internal.staff', $route->gatherMiddleware(), true))
        ->reject(fn (RoutingRoute $route): bool => collect($route->gatherMiddleware())->contains(
            fn (string $middleware): bool => str_starts_with($middleware, 'can:'),
        ))
        ->map(fn (RoutingRoute $route): string => $route->uri())
        ->unique()
        ->values()
        ->all();

    // The personal settings and the role home redirect are available to every staff member.
    expect($ungated)->toEqualCanonicalizing([
        'panel',
        'settings',
        'settings/profile',
        'settings/security',
        'settings/password',
        'settings/appearance',
    ]);
});

test('module actions are rejected with 403 when called directly by a role without the gate', function (UserRole $role, string $method, string $routeName) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)->{$method}(route($routeName), [])->assertForbidden();
})->with([
    'cajero crea una categoría' => [UserRole::Cajero, 'post', 'catalog.categories.store'],
    'cajero crea una lista de precios' => [UserRole::Cajero, 'post', 'pricing.price-lists.store'],
    'cajero crea una orden de compra' => [UserRole::Cajero, 'post', 'purchasing.orders.store'],
    'depósito crea un proveedor' => [UserRole::Deposito, 'post', 'purchasing.suppliers.store'],
    'depósito abre una caja' => [UserRole::Deposito, 'post', 'sales.cash-sessions.store'],
    'compras inicia una venta' => [UserRole::Compras, 'post', 'sales.sales.store'],
    'compras crea una sucursal' => [UserRole::Compras, 'post', 'organization.branches.store'],
    'gerente abre una caja' => [UserRole::Gerente, 'post', 'sales.cash-sessions.store'],
    'gerente crea un usuario' => [UserRole::Gerente, 'post', 'settings.users.store'],
]);

test('a cajero operates cash, sales and customers but not purchases, pricing, dashboard or users', function () {
    $cajero = User::factory()->cajero()->create();

    $this->actingAs($cajero);

    $this->get(route('sales.cash-sessions.create'))->assertSuccessful();
    $this->get(route('sales.sales.index'))->assertSuccessful();
    $this->get(route('customers.index'))->assertSuccessful();

    $this->get(route('dashboard'))->assertForbidden();
    $this->get(route('purchasing.orders.index'))->assertForbidden();
    $this->get(route('pricing.price-lists.index'))->assertForbidden();
    $this->get(route('settings.users.index'))->assertForbidden();
});

test('the shared auth.can props mirror the gates of the user role', function (UserRole $role) {
    $user = User::factory()->role($role)->create();

    $expected = collect(Permission::cases())
        ->mapWithKeys(fn (Permission $permission): array => [
            $permission->value => $role === UserRole::Administrador || $role->grants($permission),
        ])
        ->all();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.can', $expected));
})->with('internal roles');

test('the panel route sends each role to its main screen', function (UserRole $role, string $home) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)->get(route('panel'))->assertRedirect(route($home));
})->with([
    'administrador' => [UserRole::Administrador, 'dashboard'],
    'gerente' => [UserRole::Gerente, 'dashboard'],
    'compras' => [UserRole::Compras, 'purchasing.orders.index'],
    'deposito' => [UserRole::Deposito, 'ecommerce.orders.index'],
    'cajero' => [UserRole::Cajero, 'sales.cash-sessions.current'],
]);
