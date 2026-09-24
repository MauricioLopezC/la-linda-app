<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Catalog\ArticleSeeder;
use Database\Seeders\Catalog\ArticleSupplierSeeder;
use Database\Seeders\Catalog\BrandSeeder;
use Database\Seeders\Catalog\CategorySeeder;
use Database\Seeders\Catalog\UnitOfMeasureSeeder;
use Database\Seeders\Customers\CustomerSeeder;
use Database\Seeders\Inventory\StockMovementTypeSeeder;
use Database\Seeders\Inventory\WarehouseSeeder;
use Database\Seeders\Inventory\WarehouseStockSeeder;
use Database\Seeders\Organization\BranchSeeder;
use Database\Seeders\Pricing\PriceListSeeder;
use Database\Seeders\Pricing\VatRateSeeder;
use Database\Seeders\Purchasing\PurchaseOrderSeeder;
use Database\Seeders\Purchasing\SupplierSeeder;
use Database\Seeders\Purchasing\SupplierVoucherSeeder;
use Database\Seeders\Sales\PaymentMethodSeeder;
use Database\Seeders\Sales\PointOfSaleSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => config('demo.admin_email')],
            [
                'name' => config('demo.admin_name'),
                'password' => config('demo.admin_password'),
                'email_verified_at' => now(),
            ]
        );

        $this->call([
            CategorySeeder::class,
            BrandSeeder::class,
            UnitOfMeasureSeeder::class,
            BranchSeeder::class,
            WarehouseSeeder::class,
            PointOfSaleSeeder::class,
            StockMovementTypeSeeder::class,
            VatRateSeeder::class,
            PriceListSeeder::class,
            PaymentMethodSeeder::class,
            CustomerSeeder::class,
            SupplierSeeder::class,
            ArticleSeeder::class,
            ArticleSupplierSeeder::class,
            SupplierVoucherSeeder::class,
            PurchaseOrderSeeder::class,
            WarehouseStockSeeder::class,
        ]);
    }
}
