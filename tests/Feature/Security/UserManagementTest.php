<?php

declare(strict_types=1);

use App\Actions\Security\ToggleUserActive;
use App\Enums\Sales\CashSessionStatus;
use App\Enums\Security\UserRole;
use App\Models\Customers\Customer;
use App\Models\Organization\Branch;
use App\Models\Sales\CashSession;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'name' => 'Admin Principal',
        'email' => 'admin@lalinda.com',
        'role' => UserRole::Administrador,
        'is_active' => true,
    ]);
});

test('admin can view user list with filters and clients are excluded', function () {
    $branch = Branch::factory()->create(['name' => 'Sucursal Centro']);

    $cashier = User::factory()->create([
        'name' => 'Carlos Cajero',
        'email' => 'carlos@lalinda.com',
        'role' => UserRole::Cajero,
        'branch_id' => $branch->id,
        'is_active' => true,
    ]);

    $inactiveManager = User::factory()->create([
        'name' => 'Marta Gerente',
        'email' => 'marta@lalinda.com',
        'role' => UserRole::Gerente,
        'is_active' => false,
    ]);

    $client = User::factory()->client()->create([
        'name' => 'Pedro Cliente',
        'email' => 'pedro@cliente.com',
        'customer_id' => Customer::factory()->create()->id,
    ]);

    // 1. Acceso y listado general (excluye al cliente)
    $response = $this->actingAs($this->admin)->get(route('settings.users.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/users/index')
        ->has('users.data', 3) // admin + cashier + inactiveManager (client excluded)
        ->has('branches')
        ->has('roles')
    );

    // 2. Filtro por búsqueda de nombre
    $responseSearch = $this->actingAs($this->admin)->get(route('settings.users.index', ['search' => 'carlos']));
    $responseSearch->assertOk();
    $responseSearch->assertInertia(fn ($page) => $page
        ->component('settings/users/index')
        ->has('users.data', 1)
        ->where('users.data.0.id', $cashier->id)
    );

    // 3. Filtro por rol
    $responseRole = $this->actingAs($this->admin)->get(route('settings.users.index', ['role' => 'gerente']));
    $responseRole->assertOk();
    $responseRole->assertInertia(fn ($page) => $page
        ->component('settings/users/index')
        ->has('users.data', 1)
        ->where('users.data.0.id', $inactiveManager->id)
    );

    // 4. Filtro por estado inactivo
    $responseStatus = $this->actingAs($this->admin)->get(route('settings.users.index', ['status' => 'inactive']));
    $responseStatus->assertOk();
    $responseStatus->assertInertia(fn ($page) => $page
        ->component('settings/users/index')
        ->has('users.data', 1)
        ->where('users.data.0.id', $inactiveManager->id)
    );
});

test('admin can create a user with generated password', function () {
    $branch = Branch::factory()->create();

    $response = $this->actingAs($this->admin)->post(route('settings.users.store'), [
        'name' => 'Nuevo Empleado',
        'email' => 'NUEVO@LaLinda.COM',
        'role' => 'cajero',
        'branch_id' => $branch->id,
    ]);

    $response->assertRedirect();
    $response->assertInertiaFlash('generated_password');
    $response->assertSessionHas('success');

    $plainPassword = Inertia::getFlashed()['generated_password'];
    expect($plainPassword)->toBeString()->and(strlen($plainPassword))->toBeGreaterThanOrEqual(12);

    $user = User::where('email', 'nuevo@lalinda.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Nuevo Empleado')
        ->and($user->email)->toBe('nuevo@lalinda.com')
        ->and($user->role)->toBe(UserRole::Cajero)
        ->and($user->branch_id)->toBe($branch->id)
        ->and($user->is_active)->toBeTrue()
        ->and(Hash::check($plainPassword, $user->password))->toBeTrue();
});

test('admin can create a user with defined password', function () {
    $response = $this->actingAs($this->admin)->post(route('settings.users.store'), [
        'name' => 'Empleado Clave Manual',
        'email' => 'manual@lalinda.com',
        'role' => 'deposito',
        'password' => 'MiClaveSegura123',
    ]);

    $response->assertRedirect();
    $response->assertInertiaFlash('generated_password', 'MiClaveSegura123');

    $user = User::where('email', 'manual@lalinda.com')->first();
    expect($user)->not->toBeNull()
        ->and(Hash::check('MiClaveSegura123', $user->password))->toBeTrue();
});

test('admin can update a user', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'name' => 'Nombre Viejo',
        'email' => 'viejo@lalinda.com',
        'role' => UserRole::Cajero,
    ]);

    $response = $this->actingAs($this->admin)->put(route('settings.users.update', $user), [
        'name' => 'Nombre Nuevo',
        'email' => 'NUEVO_EMAIL@lalinda.com',
        'role' => 'gerente',
        'branch_id' => $branch->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $user->refresh();
    expect($user->name)->toBe('Nombre Nuevo')
        ->and($user->email)->toBe('nuevo_email@lalinda.com')
        ->and($user->role)->toBe(UserRole::Gerente)
        ->and($user->branch_id)->toBe($branch->id);
});

test('admin can reset user password with generated or manual password', function () {
    $user = User::factory()->create(['password' => Hash::make('antigua_clave')]);

    // 1. Reset con contraseña generada
    $responseGen = $this->actingAs($this->admin)->post(route('settings.users.reset-password', $user));
    $responseGen->assertRedirect();
    $responseGen->assertInertiaFlash('generated_password');
    $plainGen = Inertia::getFlashed()['generated_password'];
    expect($user->refresh()->password)->not->toBe('antigua_clave')
        ->and(Hash::check($plainGen, $user->password))->toBeTrue();

    // 2. Reset con contraseña manual
    $responseManual = $this->actingAs($this->admin)->post(route('settings.users.reset-password', $user), [
        'password' => 'NuevaClave456',
    ]);
    $responseManual->assertRedirect();
    $responseManual->assertInertiaFlash('generated_password', 'NuevaClave456');
    expect(Hash::check('NuevaClave456', $user->refresh()->password))->toBeTrue();
});

test('user cannot deactivate themselves', function () {
    $response = $this->actingAs($this->admin)
        ->patch(route('settings.users.toggle-active', $this->admin));

    $response->assertSessionHasErrors('user');
    expect($this->admin->refresh()->is_active)->toBeTrue();
});

test('cannot deactivate the last active administrator', function () {
    $secondAdmin = User::factory()->create([
        'role' => UserRole::Administrador,
        'is_active' => true,
    ]);

    // Hay 2 administradores activos: $secondAdmin puede desactivar a $this->admin
    $response = $this->actingAs($secondAdmin)
        ->patch(route('settings.users.toggle-active', $this->admin));

    $response->assertSessionHasNoErrors();
    expect($this->admin->refresh()->is_active)->toBeFalse();

    // Ahora $secondAdmin es el único administrador activo: un tercer usuario (o superadmin) no puede desactivarlo
    $superAdmin = User::factory()->create(['role' => UserRole::Administrador, 'is_active' => true]);

    // $superAdmin desactiva a $secondAdmin -> queda 1 solo admin ($superAdmin)
    $this->actingAs($superAdmin)->patch(route('settings.users.toggle-active', $secondAdmin));

    // Ahora $superAdmin es el único activo; creamos un usuario de rol compras e intentamos que intente desactivar al último admin
    $purchaser = User::factory()->create(['role' => UserRole::Compras]);
    // Simulamos llamada interna a la acción ToggleUserActive
    $action = app(ToggleUserActive::class);

    expect(fn () => $action->execute($superAdmin))
        ->toThrow(ValidationException::class);

    expect($superAdmin->refresh()->is_active)->toBeTrue();
});

test('cannot deactivate user with an open cash session', function () {
    $cashier = User::factory()->cashier()->create(['is_active' => true]);

    // Turno de caja abierto
    CashSession::factory()->create([
        'user_id' => $cashier->id,
        'status' => CashSessionStatus::Open,
    ]);

    $response = $this->actingAs($this->admin)
        ->patch(route('settings.users.toggle-active', $cashier));

    $response->assertSessionHasErrors('user');
    expect($cashier->refresh()->is_active)->toBeTrue();
});

test('can deactivate and reactivate a cashier without open sessions', function () {
    $cashier = User::factory()->cashier()->create(['is_active' => true]);

    $responseDeactivate = $this->actingAs($this->admin)
        ->patch(route('settings.users.toggle-active', $cashier));

    $responseDeactivate->assertSessionHasNoErrors();
    expect($cashier->refresh()->is_active)->toBeFalse();

    $responseReactivate = $this->actingAs($this->admin)
        ->patch(route('settings.users.toggle-active', $cashier));

    $responseReactivate->assertSessionHasNoErrors();
    expect($cashier->refresh()->is_active)->toBeTrue();
});

test('inactive user cannot login', function () {
    $inactiveUser = User::factory()->create([
        'email' => 'inactivo@lalinda.com',
        'password' => Hash::make('password123'),
        'role' => UserRole::Cajero,
        'is_active' => false,
    ]);

    $response = $this->post('/login', [
        'email' => 'inactivo@lalinda.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('middleware kicks out user if account becomes inactive during active session', function () {
    $user = User::factory()->cashier()->create(['is_active' => true]);

    $this->actingAs($user);

    // Se desactiva la cuenta en base de datos mientras tenía sesión abierta
    $user->update(['is_active' => false]);

    // En la próxima petición web, el middleware EnsureUserIsActive lo expulsa
    $response = $this->get('/dashboard');

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('non-admin user receives 403 trying to access user management', function () {
    $cashier = User::factory()->cashier()->create(['is_active' => true]);

    $response = $this->actingAs($cashier)->get(route('settings.users.index'));
    $response->assertForbidden();

    $responseStore = $this->actingAs($cashier)->post(route('settings.users.store'), [
        'name' => 'Test',
        'email' => 'test@test.com',
        'role' => 'cajero',
    ]);
    $responseStore->assertForbidden();
});

test('fortify registration is disabled for internal panel', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse();

    // GET /register de Fortify no debe estar disponible
    $response = $this->get('/register');
    $response->assertNotFound();
});

test('cannot change role of the last active administrator', function () {
    $response = $this->actingAs($this->admin)->put(route('settings.users.update', $this->admin), [
        'name' => $this->admin->name,
        'email' => $this->admin->email,
        'role' => 'cajero',
    ]);

    $response->assertSessionHasErrors('role');
    expect($this->admin->fresh()->role)->toBe(UserRole::Administrador);
});

test('creating user with uppercase of existing email returns 422 validation error not 500', function () {
    $admin = User::factory()->administrador()->create(['is_active' => true]);
    User::factory()->create(['email' => 'existente@lalinda.com']);

    $response = $this->actingAs($admin)->post(route('settings.users.store'), [
        'name' => 'Duplicado Mayúsculas',
        'email' => 'EXISTENTE@LALINDA.COM',
        'role' => 'cajero',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors('email');
    expect(User::where('name', 'Duplicado Mayúsculas')->count())->toBe(0);
});

test('updating user with uppercase of another user email returns 422 validation error not 500', function () {
    $admin = User::factory()->administrador()->create(['is_active' => true]);
    $user1 = User::factory()->cashier()->create(['email' => 'primero@lalinda.com']);
    User::factory()->cashier()->create(['email' => 'segundo@lalinda.com']);

    $response = $this->actingAs($admin)->put(route('settings.users.update', $user1), [
        'name' => $user1->name,
        'email' => 'SEGUNDO@LALINDA.COM',
        'role' => 'cajero',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors('email');
    expect($user1->fresh()->email)->toBe('primero@lalinda.com');
});

test('creating user with empty password generates and flashes password', function () {
    $admin = User::factory()->administrador()->create(['is_active' => true]);

    $response = $this->actingAs($admin)->post(route('settings.users.store'), [
        'name' => 'Auto Password User',
        'email' => 'autopass@lalinda.com',
        'role' => 'cajero',
        'password' => '',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $response->assertInertiaFlash('generated_password');
    $generatedPassword = Inertia::getFlashed()['generated_password'];
    expect($generatedPassword)->not->toBeEmpty();
    expect(strlen((string) $generatedPassword))->toBeGreaterThanOrEqual(12);
});

test('creating user with manual password flashes plain password', function () {
    $admin = User::factory()->administrador()->create(['is_active' => true]);

    $response = $this->actingAs($admin)->post(route('settings.users.store'), [
        'name' => 'Manual Password User',
        'email' => 'manualpass@lalinda.com',
        'role' => 'cajero',
        'password' => 'secret12345',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $response->assertInertiaFlash('generated_password', 'secret12345');
});

test('resetting password with empty field generates and flashes new password', function () {
    $admin = User::factory()->administrador()->create(['is_active' => true]);
    $user = User::factory()->cashier()->create();

    $response = $this->actingAs($admin)->post(route('settings.users.reset-password', $user), [
        'password' => '',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $response->assertInertiaFlash('generated_password');
    expect(Inertia::getFlashed()['generated_password'])->not->toBeEmpty();
});

test('resetting password with manual password flashes plain password', function () {
    $admin = User::factory()->administrador()->create(['is_active' => true]);
    $user = User::factory()->cashier()->create();

    $response = $this->actingAs($admin)->post(route('settings.users.reset-password', $user), [
        'password' => 'manualNewPass123',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $response->assertInertiaFlash('generated_password', 'manualNewPass123');
});

test('customer registration via tienda registro endpoint creates client and logs in', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Cliente Nuevo',
        'email' => 'CLIENTE.NUEVO@EXAMPLE.COM',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'phone' => '+54 9 387 1234567',
        'address' => 'Calle Falsa 123',
        'id_number' => '35123456',
    ]);

    $response->assertRedirect(route('tienda.home'));
    $this->assertAuthenticated();

    $createdUser = User::where('email', 'cliente.nuevo@example.com')->first();
    expect($createdUser)->not->toBeNull();
    expect($createdUser->role)->toBe(UserRole::Cliente);
    expect($createdUser->customer)->not->toBeNull();
    expect($createdUser->customer->id_number)->toBe('35123456');
});

test('creating user delivers generated password via native flash without leaking into page props (issue #99)', function () {
    $admin = User::factory()->administrador()->create(['is_active' => true]);
    $branch = Branch::factory()->create();

    $response = $this->actingAs($admin)->post(route('settings.users.store'), [
        'name' => 'Empleado Flash Test',
        'email' => 'flash-test@lalinda.com',
        'role' => 'cajero',
        'branch_id' => $branch->id,
        'password' => '',
    ]);

    $response->assertRedirect();
    $response->assertInertiaFlash('generated_password');

    // 1. Visita inmediata (redirección): la contraseña viaja en flash nativo, NO en props persistidas
    $followResponse = $this->actingAs($admin)->get(route('settings.users.index'));
    $followResponse->assertOk();
    $followResponse->assertInertia(fn (AssertableInertia $page) => $page
        ->component('settings/users/index')
        ->hasFlash('generated_password')
        ->missing('flash.generated_password')
        ->missing('generated_password')
    );

    // 2. Segunda visita (simula volver con Atrás en el historial o recargar la página): flash se consume y queda limpio
    $secondVisitResponse = $this->actingAs($admin)->get(route('settings.users.index'));
    $secondVisitResponse->assertOk();
    $secondVisitResponse->assertInertia(fn (AssertableInertia $page) => $page
        ->component('settings/users/index')
        ->missingFlash('generated_password')
        ->missing('flash.generated_password')
        ->missing('generated_password')
    );
});

test('resetting password delivers new password via native flash without leaking into page props (issue #99)', function () {
    $admin = User::factory()->administrador()->create(['is_active' => true]);
    $user = User::factory()->cashier()->create();

    $response = $this->actingAs($admin)->post(route('settings.users.reset-password', $user), [
        'password' => '',
    ]);

    $response->assertRedirect();
    $response->assertInertiaFlash('generated_password');

    // 1. Visita inmediata: flash nativo presente, props limpias
    $followResponse = $this->actingAs($admin)->get(route('settings.users.index'));
    $followResponse->assertOk();
    $followResponse->assertInertia(fn (AssertableInertia $page) => $page
        ->component('settings/users/index')
        ->hasFlash('generated_password')
        ->missing('flash.generated_password')
        ->missing('generated_password')
    );

    // 2. Visita posterior: flash limpio
    $secondVisitResponse = $this->actingAs($admin)->get(route('settings.users.index'));
    $secondVisitResponse->assertOk();
    $secondVisitResponse->assertInertia(fn (AssertableInertia $page) => $page
        ->component('settings/users/index')
        ->missingFlash('generated_password')
        ->missing('flash.generated_password')
        ->missing('generated_password')
    );
});

test('user can login with generated and reset password', function () {
    $admin = User::factory()->administrador()->create(['is_active' => true]);
    $branch = Branch::factory()->create();

    // Alta con clave generada
    $this->actingAs($admin)->post(route('settings.users.store'), [
        'name' => 'Login Test User',
        'email' => 'login-test@lalinda.com',
        'role' => 'cajero',
        'branch_id' => $branch->id,
        'password' => '',
    ]);

    $generatedPassword = Inertia::getFlashed()['generated_password'];

    // Login con clave generada
    auth()->logout();
    $loginResponse = $this->post('/login', [
        'email' => 'login-test@lalinda.com',
        'password' => $generatedPassword,
    ]);
    $loginResponse->assertRedirect();
    $this->assertAuthenticated();

    // Reset con nueva clave generada
    $user = User::where('email', 'login-test@lalinda.com')->first();
    $this->actingAs($admin)->post(route('settings.users.reset-password', $user), [
        'password' => '',
    ]);

    $resetPassword = Inertia::getFlashed()['generated_password'];
    expect($resetPassword)->not->toBe($generatedPassword);

    // Login con nueva clave reset
    auth()->logout();
    $loginResetResponse = $this->post('/login', [
        'email' => 'login-test@lalinda.com',
        'password' => $resetPassword,
    ]);
    $loginResetResponse->assertRedirect();
    $this->assertAuthenticated();
});
