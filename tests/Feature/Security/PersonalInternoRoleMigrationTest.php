<?php

use App\Enums\Security\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function personalInternoRoleMigration(): object
{
    return require database_path('migrations/2026_10_14_131608_migrate_personal_interno_role_to_administrador.php');
}

test('existing personal_interno users become administradores and clients are left untouched', function () {
    $migration = personalInternoRoleMigration();
    $migration->down();

    $staffId = DB::table('users')->insertGetId([
        'name' => 'Staff',
        'email' => 'staff@example.com',
        'password' => 'secret',
        'role' => 'personal_interno',
    ]);
    $client = User::factory()->client()->create();

    $migration->up();

    expect(DB::table('users')->where('id', $staffId)->value('role'))->toBe('administrador')
        ->and($client->fresh()->role)->toBe(UserRole::Cliente);
});

test('users inserted without a role default to cliente', function () {
    $userId = DB::table('users')->insertGetId([
        'name' => 'Sin rol',
        'email' => 'sin-rol@example.com',
        'password' => 'secret',
    ]);

    expect(DB::table('users')->where('id', $userId)->value('role'))->toBe('cliente');
});

test('rolling back folds every internal role into personal_interno', function () {
    $gerente = User::factory()->gerente()->create();
    $client = User::factory()->client()->create();

    personalInternoRoleMigration()->down();

    expect(DB::table('users')->where('id', $gerente->id)->value('role'))->toBe('personal_interno')
        ->and(DB::table('users')->where('id', $client->id)->value('role'))->toBe('cliente');
});
