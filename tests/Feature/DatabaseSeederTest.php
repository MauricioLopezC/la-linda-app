<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('seeds the configured demo admin user from config', function () {
    config([
        'demo.admin_name' => 'Demo Admin',
        'demo.admin_email' => 'demo-admin@example.com',
        'demo.admin_password' => 'super-secret-password',
    ]);

    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseHas('users', [
        'name' => 'Demo Admin',
        'email' => 'demo-admin@example.com',
    ]);
});

it('reseeding the demo admin user stays idempotent', function () {
    config(['demo.admin_email' => 'demo-admin@example.com']);

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'demo-admin@example.com')->count())->toBe(1);
});
