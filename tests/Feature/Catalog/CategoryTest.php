<?php

use App\Models\Catalog\Article;
use App\Models\Catalog\Category;
use App\Models\User;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

test('guest cannot access category management', function () {
    $this->get(route('catalog.categories.index'))->assertRedirect(route('login'));
});

test('user can view categories as a hierarchical dataset', function () {
    $user = User::factory()->create();
    $root = Category::factory()->create(['name' => 'Bebidas']);
    Category::factory()->childOf($root)->create(['name' => 'Gaseosas']);

    $this->actingAs($user)->get(route('catalog.categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/categories/index')
            ->has('categories', 2)
        );
});

test('user can create root categories and subcategories', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('catalog.categories.store'), [
        'name' => '  Bebidas  ',
        'parent_id' => 'root',
        'is_active' => true,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $root = Category::where('name_normalized', 'bebidas')->firstOrFail();

    $this->actingAs($user)->post(route('catalog.categories.store'), [
        'name' => 'Gaseosas',
        'parent_id' => $root->id,
        'is_active' => true,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('categories', ['name' => 'Bebidas', 'scope_key' => 0]);
    $this->assertDatabaseHas('categories', ['name' => 'Gaseosas', 'parent_id' => $root->id, 'scope_key' => $root->id]);
});

test('category names are unique ignoring case and outer spaces within the same level', function () {
    $user = User::factory()->create();
    Category::factory()->create(['name' => 'Lácteos']);

    $this->actingAs($user)->post(route('catalog.categories.store'), [
        'name' => '  lácteos ',
        'parent_id' => null,
    ])->assertSessionHasErrors(['name']);
});

test('sibling names are unique but may repeat under different parents', function () {
    $user = User::factory()->create();
    $firstRoot = Category::factory()->create();
    $secondRoot = Category::factory()->create();
    Category::factory()->childOf($firstRoot)->create(['name' => 'Especiales']);

    $this->actingAs($user)->post(route('catalog.categories.store'), [
        'name' => 'especiales',
        'parent_id' => $firstRoot->id,
    ])->assertSessionHasErrors(['name']);

    $this->actingAs($user)->post(route('catalog.categories.store'), [
        'name' => 'especiales',
        'parent_id' => $secondRoot->id,
    ])->assertSessionHasNoErrors();
});

test('database index protects normalized root category uniqueness', function () {
    Category::factory()->create(['name' => 'Bebidas']);

    expect(fn () => Category::factory()->create(['name' => ' BEBIDAS ']))
        ->toThrow(QueryException::class);
});

test('a subcategory cannot have children', function () {
    $user = User::factory()->create();
    $root = Category::factory()->create();
    $child = Category::factory()->childOf($root)->create();

    $this->actingAs($user)->post(route('catalog.categories.store'), [
        'name' => 'Tercer nivel',
        'parent_id' => $child->id,
    ])->assertSessionHasErrors(['parent_id']);
});

test('a root with children cannot become a subcategory', function () {
    $user = User::factory()->create();
    $root = Category::factory()->create();
    Category::factory()->childOf($root)->create();
    $newParent = Category::factory()->create();

    $this->actingAs($user)->put(route('catalog.categories.update', $root), [
        'name' => $root->name,
        'parent_id' => $newParent->id,
        'is_active' => true,
    ])->assertSessionHasErrors(['parent_id']);
});

test('user can update a category and move a child between roots', function () {
    $user = User::factory()->create();
    $firstRoot = Category::factory()->create();
    $secondRoot = Category::factory()->create();
    $child = Category::factory()->childOf($firstRoot)->create();

    $this->actingAs($user)->put(route('catalog.categories.update', $child), [
        'name' => 'Nueva subcategoría',
        'parent_id' => $secondRoot->id,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect($child->fresh())
        ->name->toBe('Nueva subcategoría')
        ->parent_id->toBe($secondRoot->id)
        ->scope_key->toBe($secondRoot->id);
});

test('active subcategory cannot be created or activated below an inactive parent', function () {
    $user = User::factory()->create();
    $root = Category::factory()->inactive()->create();

    $this->actingAs($user)->post(route('catalog.categories.store'), [
        'name' => 'Activa',
        'parent_id' => $root->id,
        'is_active' => true,
    ])->assertSessionHasErrors(['parent_id']);

    $child = Category::factory()->childOf($root)->inactive()->create();
    $this->actingAs($user)->patch(route('catalog.categories.toggle', $child))
        ->assertSessionHasErrors(['category']);
});

test('root cannot be deactivated while it has active children', function () {
    $user = User::factory()->create();
    $root = Category::factory()->create();
    Category::factory()->childOf($root)->create();

    $this->actingAs($user)->patch(route('catalog.categories.toggle', $root))
        ->assertSessionHasErrors(['category']);

    expect($root->fresh()->is_active)->toBeTrue();
});

test('category cannot be deactivated while it has associated articles', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    Article::factory()->create(['category_id' => $category->id]);

    $this->actingAs($user)->patch(route('catalog.categories.toggle', $category))
        ->assertSessionHasErrors(['category']);

    expect($category->fresh()->is_active)->toBeTrue();
});

test('category status can be toggled when business restrictions allow it', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($user)->patch(route('catalog.categories.toggle', $category))
        ->assertSessionHasNoErrors();
    expect($category->fresh()->is_active)->toBeFalse();

    $this->actingAs($user)->patch(route('catalog.categories.toggle', $category))
        ->assertSessionHasNoErrors();
    expect($category->fresh()->is_active)->toBeTrue();
});
