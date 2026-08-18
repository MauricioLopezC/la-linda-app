<?php

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\UnitOfMeasure;
use Database\Seeders\Catalog\BrandSeeder;
use Database\Seeders\Catalog\CategorySeeder;
use Database\Seeders\Catalog\UnitOfMeasureSeeder;
use Illuminate\Database\Eloquent\Model;

test('catalog demonstration seeders create the expected data and are idempotent', function () {
    $seeders = [CategorySeeder::class, BrandSeeder::class, UnitOfMeasureSeeder::class];

    Model::withoutEvents(function () use ($seeders): void {
        foreach ($seeders as $seeder) {
            $this->seed($seeder);
            $this->seed($seeder);
        }
    });

    expect(Category::count())->toBe(4)
        ->and(Brand::count())->toBe(2)
        ->and(UnitOfMeasure::count())->toBe(3);

    $warehouse = Category::where('name', 'Almacén')->firstOrFail();
    $this->assertDatabaseHas('categories', ['name' => 'Conservas', 'parent_id' => $warehouse->id]);
    $this->assertDatabaseHas('brands', ['name' => 'La Serenísima']);
    $this->assertDatabaseHas('units_of_measure', ['name' => 'Kilogramo', 'abbreviation' => 'kg']);
});
