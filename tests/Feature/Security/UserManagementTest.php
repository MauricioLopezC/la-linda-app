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
    $response->assertSessionHas('generated_password');
    $response->assertSessionHas('success');

    $plainPassword = session('generated_password');
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
    $response->assertSessionHas('generated_password', 'MiClaveSegura123');

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
    $responseGen->assertSessionHas('generated_password');
    $plainGen = session('generated_password');
    expect($user->refresh()->password)->not->toBe('antigua_clave')
        ->and(Hash::check($plainGen, $user->password))->toBeTrue();

    // 2. Reset con contraseña manual
    $responseManual = $this->actingAs($this->admin)->post(route('settings.users.reset-password', $user), [
        'password' => 'NuevaClave456',
    ]);
    $responseManual->assertRedirect();
    $responseManual->assertSessionHas('generated_password', 'NuevaClave456');
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
