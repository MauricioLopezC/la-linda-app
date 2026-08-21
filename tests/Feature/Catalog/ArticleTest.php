<?php

use App\Models\Catalog\Article;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\UnitOfMeasure;
use App\Models\Pricing\VatRate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

function articlePayload(array $overrides = []): array
{
    return array_merge([
        'description' => 'Duraznos en almíbar 820g',
        'internal_code' => 'ART-0001',
        'barcode' => null,
        'category_id' => Category::factory()->create()->id,
        'brand_id' => null,
        'unit_of_measure_id' => UnitOfMeasure::factory()->create()->id,
        'vat_rate_id' => VatRate::factory()->create()->id,
        'status' => 'active',
        'is_online_publishable' => false,
    ], $overrides);
}

test('guest cannot access article management', function () {
    $this->get(route('catalog.articles.index'))->assertRedirect(route('login'));
});

test('user can view article management', function () {
    $user = User::factory()->create();
    Article::factory()->count(2)->create();

    $this->actingAs($user)->get(route('catalog.articles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/articles/index')
            ->has('articles', 2)
        );
});

test('user can create an article with only the required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('catalog.articles.store'), articlePayload([
        'barcode' => null,
        'brand_id' => null,
    ]))->assertSessionHasNoErrors();

    $article = Article::firstOrFail();
    expect($article->barcode)->toBeNull();
    expect($article->brand_id)->toBeNull();
    expect($article->status->value)->toBe('active');
});

test('user can create and update an article with all fields', function () {
    $user = User::factory()->create();
    $brand = Brand::factory()->create();

    $this->actingAs($user)->post(route('catalog.articles.store'), articlePayload([
        'description' => '  Duraznos en almíbar 820g ',
        'internal_code' => ' ART-0001 ',
        'barcode' => ' 7790895000017 ',
        'brand_id' => $brand->id,
        'is_online_publishable' => true,
    ]))->assertSessionHasNoErrors();

    $article = Article::firstOrFail();
    expect($article->description)->toBe('Duraznos en almíbar 820g');
    expect($article->internal_code)->toBe('ART-0001');
    expect($article->barcode)->toBe('7790895000017');
    expect($article->brand_id)->toBe($brand->id);
    expect($article->is_online_publishable)->toBeTrue();

    $newCategory = Category::factory()->create();
    $newUnit = UnitOfMeasure::factory()->create();
    $newVatRate = VatRate::factory()->create();

    $this->actingAs($user)->put(route('catalog.articles.update', $article), articlePayload([
        'description' => 'Duraznos en almíbar 820g (actualizado)',
        'internal_code' => $article->internal_code,
        'barcode' => $article->barcode,
        'category_id' => $newCategory->id,
        'brand_id' => null,
        'unit_of_measure_id' => $newUnit->id,
        'vat_rate_id' => $newVatRate->id,
        'status' => 'inactive',
    ]))->assertSessionHasNoErrors();

    expect($article->fresh())
        ->description->toBe('Duraznos en almíbar 820g (actualizado)')
        ->category_id->toBe($newCategory->id)
        ->brand_id->toBeNull()
        ->unit_of_measure_id->toBe($newUnit->id)
        ->vat_rate_id->toBe($newVatRate->id);
    expect($article->fresh()->status->value)->toBe('inactive');
});

test('an article can be assigned to a subcategory', function () {
    $user = User::factory()->create();
    $root = Category::factory()->create();
    $subcategory = Category::factory()->childOf($root)->create();

    $this->actingAs($user)->post(route('catalog.articles.store'), articlePayload([
        'category_id' => $subcategory->id,
    ]))->assertSessionHasNoErrors();

    expect(Article::firstOrFail()->category_id)->toBe($subcategory->id);
});

test('internal code is unique ignoring case and outer spaces', function () {
    $user = User::factory()->create();
    Article::factory()->create(['internal_code' => 'ART-0001']);

    $this->actingAs($user)->post(route('catalog.articles.store'), articlePayload([
        'internal_code' => '  art-0001 ',
    ]))->assertSessionHasErrors(['internal_code']);
});

test('database index protects normalized internal code uniqueness', function () {
    Article::factory()->create(['internal_code' => 'ART-0001']);

    expect(fn () => Article::factory()->create(['internal_code' => ' art-0001 ']))
        ->toThrow(QueryException::class);
});

test('barcode is unique when informed, ignoring case and outer spaces', function () {
    $user = User::factory()->create();
    Article::factory()->create(['barcode' => '7790895000017']);

    $this->actingAs($user)->post(route('catalog.articles.store'), articlePayload([
        'internal_code' => 'ART-0002',
        'barcode' => ' 7790895000017 ',
    ]))->assertSessionHasErrors(['barcode']);
});

test('database index protects normalized barcode uniqueness', function () {
    Article::factory()->create(['barcode' => '7790895000017']);

    expect(fn () => Article::factory()->create(['barcode' => ' 7790895000017 ']))
        ->toThrow(QueryException::class);
});

test('barcode uniqueness is not enforced when it is left blank', function () {
    $user = User::factory()->create();
    Article::factory()->create(['barcode' => null, 'internal_code' => 'ART-0001']);

    $this->actingAs($user)->post(route('catalog.articles.store'), articlePayload([
        'internal_code' => 'ART-0002',
        'barcode' => null,
    ]))->assertSessionHasNoErrors();

    expect(Article::query()->whereNull('barcode')->count())->toBe(2);
});

test('clearing a previously set barcode frees it for reuse by another article', function () {
    $user = User::factory()->create();
    $article = Article::factory()->create(['barcode' => '7790895000017']);

    $this->actingAs($user)->put(route('catalog.articles.update', $article), articlePayload([
        'internal_code' => $article->internal_code,
        'barcode' => null,
    ]))->assertSessionHasNoErrors();

    expect($article->fresh()->barcode)->toBeNull();

    $this->actingAs($user)->post(route('catalog.articles.store'), articlePayload([
        'internal_code' => 'ART-0002',
        'barcode' => '7790895000017',
    ]))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('articles', ['internal_code' => 'ART-0002', 'barcode' => '7790895000017']);
});

test('description, internal code, category, unit of measure and vat rate are required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('catalog.articles.store'), articlePayload([
        'description' => '',
        'internal_code' => '',
        'category_id' => '',
        'unit_of_measure_id' => '',
        'vat_rate_id' => '',
    ]))->assertSessionHasErrors(['description', 'internal_code', 'category_id', 'unit_of_measure_id', 'vat_rate_id']);
});

test('brand and barcode are not required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('catalog.articles.store'), articlePayload([
        'brand_id' => null,
        'barcode' => null,
    ]))->assertSessionHasNoErrors();
});

test('status can be set freely to active, inactive or discontinued from the form', function () {
    $user = User::factory()->create();
    $article = Article::factory()->create();

    foreach (['inactive', 'discontinued', 'active'] as $status) {
        $this->actingAs($user)->put(route('catalog.articles.update', $article), articlePayload([
            'internal_code' => $article->internal_code,
            'status' => $status,
        ]))->assertSessionHasNoErrors();

        expect($article->fresh()->status->value)->toBe($status);
    }
});

test('dar de baja an article sets it to discontinued without deleting it', function () {
    $user = User::factory()->create();
    $article = Article::factory()->create();

    $this->actingAs($user)->delete(route('catalog.articles.destroy', $article))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('articles', ['id' => $article->id]);
    expect($article->fresh()->status->value)->toBe('discontinued');
});

test('dar de baja an already discontinued article keeps it discontinued', function () {
    $user = User::factory()->create();
    $article = Article::factory()->discontinued()->create();

    $this->actingAs($user)->delete(route('catalog.articles.destroy', $article))
        ->assertSessionHasNoErrors();

    expect($article->fresh()->status->value)->toBe('discontinued');
});
