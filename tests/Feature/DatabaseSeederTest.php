<?php

use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Pricing\PriceList;
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

it('seeds demo prices that exercise every step of the price cascade', function () {
    $this->seed(DatabaseSeeder::class);

    $general = PriceList::query()->where('name_normalized', 'lista general')->sole();
    $mostrador = PriceList::query()->where('name_normalized', 'lista mostrador')->sole();
    $mayorista = PriceList::query()->where('name_normalized', 'mayorista')->sole();
    $activeArticles = Article::query()->active()->count();

    expect($general->items()->count())->toBe($activeArticles - 1)
        ->and($mostrador->items()->count())->toBeGreaterThan(0)->toBeLessThan($general->items()->count())
        ->and($mayorista->items()->count())->toBe($general->items()->count())
        ->and(Customer::query()->where('id_number', '30500858628')->value('price_list_id'))->toBe($mayorista->id);
});
