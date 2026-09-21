<?php

use App\Models\Catalog\Article;
use App\Models\Catalog\ArticleSupplier;
use App\Models\Purchasing\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guest cannot attach or manage article suppliers', function () {
    $article = Article::factory()->create();
    $supplier = Supplier::factory()->create();

    $this->post(route('catalog.articles.suppliers.store', $article), [
        'supplier_id' => $supplier->id,
        'supplier_article_code' => 'COD-1',
    ])->assertRedirect(route('login'));

    $this->post(route('purchasing.suppliers.articles.store', $supplier), [
        'article_id' => $article->id,
        'supplier_article_code' => 'COD-1',
    ])->assertRedirect(route('login'));
});

test('user can attach a supplier to an article with valid data', function () {
    $user = User::factory()->create();
    $article = Article::factory()->create();
    $supplier = Supplier::factory()->create();

    $this->actingAs($user)->post(route('catalog.articles.suppliers.store', $article), [
        'supplier_id' => $supplier->id,
        'supplier_article_code' => 'ART-SUP-001',
        'notes' => 'Proveedor preferencial',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('article_supplier', [
        'article_id' => $article->id,
        'supplier_id' => $supplier->id,
        'supplier_article_code' => 'ART-SUP-001',
        'supplier_article_code_normalized' => 'art-sup-001',
        'last_cost' => null,
        'notes' => 'Proveedor preferencial',
    ]);
});

test('user can attach an article from the supplier perspective', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $article = Article::factory()->create();

    $this->actingAs($user)->post(route('purchasing.suppliers.articles.store', $supplier), [
        'article_id' => $article->id,
        'supplier_article_code' => 'SUP-CODE-99',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('article_supplier', [
        'article_id' => $article->id,
        'supplier_id' => $supplier->id,
        'supplier_article_code' => 'SUP-CODE-99',
        'supplier_article_code_normalized' => 'sup-code-99',
        'last_cost' => null,
    ]);
});

test('many-to-many relationship works bidirectionally', function () {
    $article1 = Article::factory()->create();
    $article2 = Article::factory()->create();
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    ArticleSupplier::factory()->create([
        'article_id' => $article1->id,
        'supplier_id' => $supplier1->id,
        'supplier_article_code' => 'A1-S1',
    ]);
    ArticleSupplier::factory()->create([
        'article_id' => $article1->id,
        'supplier_id' => $supplier2->id,
        'supplier_article_code' => 'A1-S2',
    ]);
    ArticleSupplier::factory()->create([
        'article_id' => $article2->id,
        'supplier_id' => $supplier1->id,
        'supplier_article_code' => 'A2-S1',
    ]);

    expect($article1->suppliers)->toHaveCount(2);
    expect($article2->suppliers)->toHaveCount(1);
    expect($supplier1->articles)->toHaveCount(2);
    expect($supplier2->articles)->toHaveCount(1);
});

test('cannot attach the same supplier to an article more than once', function () {
    $user = User::factory()->create();
    $article = Article::factory()->create();
    $supplier = Supplier::factory()->create();

    ArticleSupplier::factory()->create([
        'article_id' => $article->id,
        'supplier_id' => $supplier->id,
        'supplier_article_code' => 'CODE-A',
    ]);

    $this->actingAs($user)
        ->from(route('catalog.articles.index'))
        ->post(route('catalog.articles.suppliers.store', $article), [
            'supplier_id' => $supplier->id,
            'supplier_article_code' => 'CODE-B',
        ])
        ->assertSessionHasErrors('supplier_id');

    $this->actingAs($user)
        ->from(route('purchasing.suppliers.index'))
        ->post(route('purchasing.suppliers.articles.store', $supplier), [
            'article_id' => $article->id,
            'supplier_article_code' => 'CODE-C',
        ])
        ->assertSessionHasErrors('article_id');

    expect(ArticleSupplier::where('article_id', $article->id)->where('supplier_id', $supplier->id)->count())->toBe(1);
});

test('supplier article code must be unique within the same supplier', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $article1 = Article::factory()->create();
    $article2 = Article::factory()->create();

    ArticleSupplier::factory()->create([
        'article_id' => $article1->id,
        'supplier_id' => $supplier->id,
        'supplier_article_code' => 'CODIGO-UNICO',
    ]);

    // Same code with different case and whitespace should be rejected
    $this->actingAs($user)
        ->from(route('catalog.articles.index'))
        ->post(route('catalog.articles.suppliers.store', $article2), [
            'supplier_id' => $supplier->id,
            'supplier_article_code' => '  codigo-unico  ',
        ])
        ->assertSessionHasErrors('supplier_article_code');
});

test('different suppliers can use the same article code', function () {
    $user = User::factory()->create();
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();
    $article1 = Article::factory()->create();
    $article2 = Article::factory()->create();

    ArticleSupplier::factory()->create([
        'article_id' => $article1->id,
        'supplier_id' => $supplier1->id,
        'supplier_article_code' => 'MISMO-CODIGO',
    ]);

    $this->actingAs($user)
        ->post(route('catalog.articles.suppliers.store', $article2), [
            'supplier_id' => $supplier2->id,
            'supplier_article_code' => 'MISMO-CODIGO',
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('article_supplier', [
        'article_id' => $article2->id,
        'supplier_id' => $supplier2->id,
        'supplier_article_code' => 'MISMO-CODIGO',
    ]);
});

test('last cost cannot be entered manually when associating articles and suppliers', function () {
    $user = User::factory()->create();
    $article = Article::factory()->create();
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();
    // Omitting the cost leaves it unknown.
    $this->actingAs($user)->post(route('catalog.articles.suppliers.store', $article), [
        'supplier_id' => $supplier1->id,
        'supplier_article_code' => 'COD-NULL',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('article_supplier', [
        'article_id' => $article->id,
        'supplier_id' => $supplier1->id,
        'last_cost' => null,
    ]);

    // Any manually supplied cost is forbidden.
    $this->actingAs($user)->post(route('catalog.articles.suppliers.store', $article), [
        'supplier_id' => $supplier2->id,
        'supplier_article_code' => 'COD-ZERO',
        'last_cost' => '20.00',
    ])->assertSessionHasErrors('last_cost');

    $this->assertDatabaseMissing('article_supplier', ['supplier_id' => $supplier2->id]);

    $this->actingAs($user)->post(route('purchasing.suppliers.articles.store', $supplier2), [
        'article_id' => $article->id,
        'supplier_article_code' => 'COD-PROV',
        'last_cost' => '20.00',
    ])->assertSessionHasErrors('last_cost');
});

test('cannot associate an inactive supplier or inactive article', function () {
    $user = User::factory()->create();
    $article = Article::factory()->create(['status' => 'inactive']);
    $supplier = Supplier::factory()->create(['is_active' => false]);
    $activeArticle = Article::factory()->create(['status' => 'active']);
    $activeSupplier = Supplier::factory()->create(['is_active' => true]);

    $this->actingAs($user)->post(route('catalog.articles.suppliers.store', $activeArticle), [
        'supplier_id' => $supplier->id,
        'supplier_article_code' => 'COD-1',
    ])->assertSessionHasErrors('supplier_id');

    $this->actingAs($user)->post(route('purchasing.suppliers.articles.store', $activeSupplier), [
        'article_id' => $article->id,
        'supplier_article_code' => 'COD-2',
    ])->assertSessionHasErrors('article_id');
});

test('user can update an association from article or supplier perspective', function () {
    $user = User::factory()->create();
    $article = Article::factory()->create();
    $supplier = Supplier::factory()->create();

    $pivot = ArticleSupplier::factory()->create([
        'article_id' => $article->id,
        'supplier_id' => $supplier->id,
        'supplier_article_code' => 'OLD-CODE',
        'last_cost' => '50.00',
    ]);

    // Update from article route
    $this->actingAs($user)->put(route('catalog.articles.suppliers.update', [
        'article' => $article,
        'supplier' => $supplier,
    ]), [
        'supplier_article_code' => 'NEW-CODE',
        'notes' => 'Actualizado desde artículo',
    ])->assertSessionHasNoErrors();

    $pivot->refresh();
    expect($pivot->supplier_article_code)->toBe('NEW-CODE');
    expect((float) $pivot->last_cost)->toBe(50.0);
    expect($pivot->notes)->toBe('Actualizado desde artículo');

    // Update from supplier route
    $this->actingAs($user)->put(route('purchasing.suppliers.articles.update', [
        'supplier' => $supplier,
        'article' => $article,
    ]), [
        'supplier_article_code' => 'NEW-CODE-2',
        'notes' => 'Actualizado desde proveedor',
    ])->assertSessionHasNoErrors();

    $pivot->refresh();
    expect($pivot->supplier_article_code)->toBe('NEW-CODE-2');
    expect((float) $pivot->last_cost)->toBe(50.0);
});

test('manual cost changes are rejected from both association endpoints', function () {
    $user = User::factory()->create();
    $article = Article::factory()->create();
    $supplier = Supplier::factory()->create();
    $association = ArticleSupplier::factory()->create([
        'article_id' => $article->id,
        'supplier_id' => $supplier->id,
        'last_cost' => '50.00',
    ]);

    $this->actingAs($user)->put(route('catalog.articles.suppliers.update', [
        'article' => $article,
        'supplier' => $supplier,
    ]), [
        'supplier_article_code' => $association->supplier_article_code,
        'last_cost' => '75.00',
    ])->assertSessionHasErrors('last_cost');

    $this->actingAs($user)->put(route('purchasing.suppliers.articles.update', [
        'supplier' => $supplier,
        'article' => $article,
    ]), [
        'supplier_article_code' => $association->supplier_article_code,
        'last_cost' => '90.00',
    ])->assertSessionHasErrors('last_cost');

    expect($association->fresh()->last_cost)->toBe('50.00');
});

test('updating an association ignores its own code for uniqueness but rejects existing code', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $article1 = Article::factory()->create();
    $article2 = Article::factory()->create();

    ArticleSupplier::factory()->create([
        'article_id' => $article1->id,
        'supplier_id' => $supplier->id,
        'supplier_article_code' => 'CODE-1',
    ]);

    ArticleSupplier::factory()->create([
        'article_id' => $article2->id,
        'supplier_id' => $supplier->id,
        'supplier_article_code' => 'CODE-2',
    ]);

    // Updating article1 with same code CODE-1 should succeed
    $this->actingAs($user)->put(route('catalog.articles.suppliers.update', [
        'article' => $article1,
        'supplier' => $supplier,
    ]), [
        'supplier_article_code' => 'CODE-1',
    ])->assertSessionHasNoErrors();

    // Updating article2 to CODE-1 should fail
    $this->actingAs($user)->put(route('catalog.articles.suppliers.update', [
        'article' => $article2,
        'supplier' => $supplier,
    ]), [
        'supplier_article_code' => 'CODE-1',
    ])->assertSessionHasErrors('supplier_article_code');
});

test('user can detach a supplier from an article', function () {
    $user = User::factory()->create();
    $article = Article::factory()->create();
    $supplier = Supplier::factory()->create();

    ArticleSupplier::factory()->create([
        'article_id' => $article->id,
        'supplier_id' => $supplier->id,
        'supplier_article_code' => 'TO-DELETE',
    ]);

    $this->actingAs($user)
        ->delete(route('catalog.articles.suppliers.destroy', [
            'article' => $article,
            'supplier' => $supplier,
        ]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('article_supplier', [
        'article_id' => $article->id,
        'supplier_id' => $supplier->id,
    ]);

    // After detaching, the code can be reused
    $this->actingAs($user)->post(route('catalog.articles.suppliers.store', $article), [
        'supplier_id' => $supplier->id,
        'supplier_article_code' => 'TO-DELETE',
    ])->assertSessionHasNoErrors();
});

test('user can detach an article from a supplier', function () {
    $user = User::factory()->create();
    $article = Article::factory()->create();
    $supplier = Supplier::factory()->create();

    ArticleSupplier::factory()->create([
        'article_id' => $article->id,
        'supplier_id' => $supplier->id,
        'supplier_article_code' => 'SUP-DEL',
    ]);

    $this->actingAs($user)
        ->delete(route('purchasing.suppliers.articles.destroy', [
            'supplier' => $supplier,
            'article' => $article,
        ]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('article_supplier', [
        'article_id' => $article->id,
        'supplier_id' => $supplier->id,
    ]);
});

test('index pages include supplier/article association data', function () {
    $user = User::factory()->create();
    $article = Article::factory()->create();
    $supplier = Supplier::factory()->create();

    ArticleSupplier::factory()->create([
        'article_id' => $article->id,
        'supplier_id' => $supplier->id,
        'supplier_article_code' => 'IDX-CODE',
        'last_cost' => '120.00',
    ]);

    $this->actingAs($user)->get(route('catalog.articles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/articles/index')
            ->has('articles.0.suppliers', 1)
            ->where('articles.0.suppliers.0.supplier_article_code', 'IDX-CODE')
            ->has('availableSuppliers')
        );

    $this->actingAs($user)->get(route('purchasing.suppliers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/suppliers/index')
            ->has('suppliers.0.articles', 1)
            ->where('suppliers.0.articles.0.supplier_article_code', 'IDX-CODE')
            ->has('availableArticles')
        );
});
