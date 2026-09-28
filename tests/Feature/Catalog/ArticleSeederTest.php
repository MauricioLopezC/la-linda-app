<?php

use App\Models\Catalog\Article;
use Database\Seeders\Catalog\ArticleSeeder;
use Database\Seeders\Catalog\BrandSeeder;
use Database\Seeders\Catalog\CategorySeeder;
use Database\Seeders\Catalog\UnitOfMeasureSeeder;
use Database\Seeders\Pricing\VatRateSeeder;

test('closed-package seed articles do not use a unit that allows fractional stock', function () {
    $this->seed([
        CategorySeeder::class,
        BrandSeeder::class,
        UnitOfMeasureSeeder::class,
        VatRateSeeder::class,
        ArticleSeeder::class,
    ]);

    $closedPackageTypes = ['Lata', 'Botella', 'Paquete', 'Bolsa', 'Tetra Brik', 'Pote', 'Doypack'];

    $mismatched = Article::query()
        ->with('unitOfMeasure')
        ->get()
        ->filter(function (Article $article) use ($closedPackageTypes): bool {
            $hasClosedPackaging = collect($closedPackageTypes)
                ->contains(fn (string $type): bool => str_contains($article->description, $type));

            return $hasClosedPackaging && $article->allowsDecimalQuantity();
        });

    expect($mismatched)->toBeEmpty(
        'Artículos en envase cerrado (peso/volumen fijo) no deben usar una unidad que admita '
        .'ajustes de stock fraccionarios: '.$mismatched->pluck('internal_code')->implode(', ')
    );
});

test('seed articles carry the reduced VAT rate for fresh produce and the general one otherwise', function () {
    $this->seed([
        CategorySeeder::class,
        BrandSeeder::class,
        UnitOfMeasureSeeder::class,
        VatRateSeeder::class,
        ArticleSeeder::class,
    ]);

    $percentages = Article::query()
        ->with(['vatRate', 'category'])
        ->get()
        ->mapWithKeys(fn (Article $article): array => [$article->internal_code => $article->vatRate?->percentage]);

    expect($percentages)->not->toContain(null)
        ->and($percentages['ART-0016'])->toBe(10.5)
        ->and($percentages['ART-0001'])->toBe(21.0);
});
