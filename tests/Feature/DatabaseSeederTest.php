<?php

use App\Enums\Security\UserRole;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Pricing\PriceList;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

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
        'role' => 'administrador',
    ]);
});

it('reseeding the demo admin user stays idempotent', function () {
    config(['demo.admin_email' => 'demo-admin@example.com']);

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'demo-admin@example.com')->count())->toBe(1);
});

it('seeds one demo staff user per operational role', function () {
    config([
        'demo.staff_email_domain' => 'demo.test',
        'demo.staff_password' => 'staff-secret-password',
    ]);

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    foreach ([UserRole::Gerente, UserRole::Compras, UserRole::Deposito, UserRole::Cajero] as $role) {
        $user = User::query()->where('email', "{$role->value}@demo.test")->sole();

        expect($user->role)->toBe($role)
            ->and($user->is_active)->toBeTrue()
            ->and($user->branch_id)->not->toBeNull()
            ->and(Hash::check('staff-secret-password', $user->password))->toBeTrue();
    }
});

it('seeds demo prices that exercise every step of the price cascade', function () {
    $this->seed(DatabaseSeeder::class);

    $general = PriceList::query()->where('name_normalized', 'lista general')->sole();
    $mostrador = PriceList::query()->where('name_normalized', 'lista mostrador')->sole();
    $mayorista = PriceList::query()->where('name_normalized', 'mayorista')->sole();
    $activeArticles = Article::query()->active()->count();

    expect($general->items()->count())->toBe($activeArticles - 1)
        ->and($mostrador->items()->count())->toBeGreaterThan(0)->toBeLessThan($general->items()->count())
        ->and($mayorista->items()->count())->toBe($general->items()->count())
        ->and(Customer::query()->where('id_number', '30716485273')->value('price_list_id'))->toBe($mayorista->id);
});
