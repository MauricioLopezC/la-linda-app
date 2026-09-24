<?php

use App\Models\Catalog\ArticleSupplier;
use App\Models\Purchasing\Supplier;
use Database\Seeders\Catalog\ArticleSeeder;
use Database\Seeders\Catalog\ArticleSupplierSeeder;
use Database\Seeders\Catalog\BrandSeeder;
use Database\Seeders\Catalog\CategorySeeder;
use Database\Seeders\Catalog\UnitOfMeasureSeeder;
use Database\Seeders\Purchasing\SupplierSeeder;

beforeEach(function () {
    $this->seed([
        CategorySeeder::class,
        BrandSeeder::class,
        UnitOfMeasureSeeder::class,
        SupplierSeeder::class,
        ArticleSeeder::class,
    ]);
});

test('article supplier seeder associates demo articles with their suppliers', function () {
    $this->seed(ArticleSupplierSeeder::class);

    expect(ArticleSupplier::query()->count())->toBe(18);

    $arcor = Supplier::query()->where('tax_id', '30502793175')->sole();
    $durazno = ArticleSupplier::query()
        ->whereBelongsTo($arcor)
        ->whereRelation('article', 'internal_code', 'ART-0001')
        ->sole();

    expect($durazno->supplier_article_code)->toBe('ARC-7701')
        ->and($durazno->supplier_article_code_normalized)->toBe(ArticleSupplier::normalizeUniqueValue('ARC-7701'))
        ->and($durazno->last_cost)->toBeNull();
});

test('article supplier seeder is idempotent and keeps codes edited after seeding', function () {
    $this->seed(ArticleSupplierSeeder::class);

    $association = ArticleSupplier::query()->where('supplier_article_code', 'MRP-10450')->sole();
    $association->update(['supplier_article_code' => 'MRP-EDITADO']);

    $this->seed(ArticleSupplierSeeder::class);

    expect(ArticleSupplier::query()->count())->toBe(18)
        ->and($association->fresh()->supplier_article_code)->toBe('MRP-EDITADO');
});
