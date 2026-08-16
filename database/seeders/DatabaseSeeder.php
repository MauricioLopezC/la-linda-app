<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Inventory\WarehouseSeeder;
use Database\Seeders\Organization\BranchSeeder;
use Database\Seeders\Sales\PointOfSaleSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            BranchSeeder::class,
            WarehouseSeeder::class,
            PointOfSaleSeeder::class,
        ]);
    }
}
