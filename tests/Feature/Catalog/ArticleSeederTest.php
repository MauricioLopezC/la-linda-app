<?php

use App\Models\Catalog\Article;
use Database\Seeders\Catalog\ArticleSeeder;
use Database\Seeders\Catalog\BrandSeeder;
use Database\Seeders\Catalog\CategorySeeder;
use Database\Seeders\Catalog\UnitOfMeasureSeeder;

test('closed-package seed articles do not use a unit that allows fractional stock', function () {
    $this->seed([
        CategorySeeder::class,
        BrandSeeder::class,
        UnitOfMeasureSeeder::class,
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
