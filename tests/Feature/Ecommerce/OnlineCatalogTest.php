<?php

use App\Enums\Catalog\ArticleStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Pricing\PriceListScope;
use App\Models\Catalog\Article;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\UnitOfMeasure;
use App\Models\Customers\Customer;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guest can view online catalog with publishable articles and resolved prices', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create(['name' => 'Lista Online Principal']);

    $category = Category::factory()->create(['name' => 'Almacén', 'is_active' => true]);
    $brand = Brand::factory()->create(['name' => 'Arcor', 'is_active' => true]);
    $unit = UnitOfMeasure::factory()->create(['name' => 'Unidad', 'abbreviation' => 'u', 'is_active' => true]);

    $article = Article::factory()->create([
        'description' => 'Mermelada de Frutilla 400g',
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'unit_of_measure_id' => $unit->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $article->id,
        'price' => '1500.00',
    ]);

    $response = $this->get(route('tienda.home'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->has('articles.data', 1)
        ->where('articles.data.0.id', $article->id)
        ->where('articles.data.0.description', 'Mermelada de Frutilla 400g')
        ->where('articles.data.0.brand_name', 'Arcor')
        ->where('articles.data.0.category_name', 'Almacén')
        ->where('articles.data.0.unit_of_measure_name', 'Unidad')
        ->where('articles.data.0.price', '1500.00')
        ->where('articles.data.0.formatted_price', '$ 1.500,00')
        ->where('articles.data.0.is_particular_price', false)
        ->where('articles.total', 1)
        ->has('categories')
    );
});

test('inactive articles are not shown in online catalog', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $activeArticle = Article::factory()->create([
        'description' => 'Fideos Tallarines 500g',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    $inactiveArticle = Article::factory()->create([
        'description' => 'Fideos Mostacholes 500g',
        'status' => ArticleStatus::Inactive,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $activeArticle->id,
        'price' => '850.00',
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $inactiveArticle->id,
        'price' => '850.00',
    ]);

    $response = $this->get(route('tienda.home'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->has('articles.data', 1)
        ->where('articles.data.0.id', $activeArticle->id)
        ->where('articles.total', 1)
    );
});

test('articles not marked as online publishable are not shown in online catalog', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $publishableArticle = Article::factory()->create([
        'description' => 'Aceite de Girasol 1.5L',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    $nonPublishableArticle = Article::factory()->create([
        'description' => 'Aceite de Maíz 1.5L',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => false,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $publishableArticle->id,
        'price' => '2100.00',
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $nonPublishableArticle->id,
        'price' => '2300.00',
    ]);

    $response = $this->get(route('tienda.home'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->has('articles.data', 1)
        ->where('articles.data.0.id', $publishableArticle->id)
        ->where('articles.total', 1)
    );
});

test('articles without price in any applicable list are not shown in catalog', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $pricedArticle = Article::factory()->create([
        'description' => 'Galletitas de Agua 300g',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    $unpricedArticle = Article::factory()->create([
        'description' => 'Galletitas Dulces 300g',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $pricedArticle->id,
        'price' => '650.00',
    ]);

    $response = $this->get(route('tienda.home'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->has('articles.data', 1)
        ->where('articles.data.0.id', $pricedArticle->id)
        ->where('articles.total', 1)
    );
});

test('article with online price list displays online price over general list', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create(['name' => 'Lista Online']);

    $generalList = PriceList::factory()
        ->forChannel(PriceListChannel::General)
        ->create(['name' => 'Lista General']);

    $article = Article::factory()->create([
        'description' => 'Arroz Largo Fino 1kg',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $article->id,
        'price' => '1200.00',
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $generalList->id,
        'article_id' => $article->id,
        'price' => '1400.00',
    ]);

    $response = $this->get(route('tienda.home'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->where('articles.data.0.price', '1200.00')
        ->where('articles.data.0.formatted_price', '$ 1.200,00')
        ->where('articles.data.0.price_list_name', 'Lista Online')
        ->where('articles.data.0.is_particular_price', false)
    );
});

test('article without online price list falls back to general price list', function () {
    PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create(['name' => 'Lista Online']);

    $generalList = PriceList::factory()
        ->forChannel(PriceListChannel::General)
        ->create(['name' => 'Lista General']);

    $article = Article::factory()->create([
        'description' => 'Yerba Mate 1kg',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $generalList->id,
        'article_id' => $article->id,
        'price' => '2500.00',
    ]);

    $response = $this->get(route('tienda.home'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->where('articles.data.0.price', '2500.00')
        ->where('articles.data.0.formatted_price', '$ 2.500,00')
        ->where('articles.data.0.price_list_name', 'Lista General')
        ->where('articles.data.0.is_particular_price', false)
    );
});

test('authenticated client with particular price list sees preferential price for priced articles', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create(['name' => 'Lista Online']);

    $particularList = PriceList::factory()->create([
        'name' => 'Lista Mayorista Preferencial',
        'scope' => PriceListScope::Particular,
        'channel' => null,
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create([
        'price_list_id' => $particularList->id,
    ]);

    $user = User::factory()->client()->create([
        'customer_id' => $customer->id,
    ]);

    $article = Article::factory()->create([
        'description' => 'Café Tostado en Granos 500g',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $particularList->id,
        'article_id' => $article->id,
        'price' => '3200.00',
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $article->id,
        'price' => '4000.00',
    ]);

    $response = $this->actingAs($user)->get(route('tienda.home'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->where('articles.data.0.price', '3200.00')
        ->where('articles.data.0.formatted_price', '$ 3.200,00')
        ->where('articles.data.0.price_list_name', 'Lista Mayorista Preferencial')
        ->where('articles.data.0.is_particular_price', true)
    );
});

test('authenticated client sees online channel price for articles not in their particular list', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create(['name' => 'Lista Online']);

    $particularList = PriceList::factory()->create([
        'name' => 'Lista VIP',
        'scope' => PriceListScope::Particular,
        'channel' => null,
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create([
        'price_list_id' => $particularList->id,
    ]);

    $user = User::factory()->client()->create([
        'customer_id' => $customer->id,
    ]);

    $article = Article::factory()->create([
        'description' => 'Té Negro en Saquitos x50',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    // Article priced only in online list, not in particular list
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $article->id,
        'price' => '1100.00',
    ]);

    $response = $this->actingAs($user)->get(route('tienda.home'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->where('articles.data.0.price', '1100.00')
        ->where('articles.data.0.price_list_name', 'Lista Online')
        ->where('articles.data.0.is_particular_price', false)
    );
});

test('expired particular price list is ignored and falls back to online channel', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create(['name' => 'Lista Online']);

    $expiredParticularList = PriceList::factory()->vencida()->create([
        'name' => 'Lista Caducada',
        'scope' => PriceListScope::Particular,
        'channel' => null,
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create([
        'price_list_id' => $expiredParticularList->id,
    ]);

    $user = User::factory()->client()->create([
        'customer_id' => $customer->id,
    ]);

    $article = Article::factory()->create([
        'description' => 'Azúcar Ledesma 1kg',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $expiredParticularList->id,
        'article_id' => $article->id,
        'price' => '600.00',
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $article->id,
        'price' => '900.00',
    ]);

    $response = $this->actingAs($user)->get(route('tienda.home'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->where('articles.data.0.price', '900.00')
        ->where('articles.data.0.price_list_name', 'Lista Online')
        ->where('articles.data.0.is_particular_price', false)
    );
});

test('search by description filters articles case-insensitively', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $milkArticle = Article::factory()->create([
        'description' => 'Leche Entera Larga Vida 1L',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    $juiceArticle = Article::factory()->create([
        'description' => 'Jugo de Naranja 1L',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $milkArticle->id,
        'price' => '1000.00',
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $juiceArticle->id,
        'price' => '950.00',
    ]);

    $response = $this->get(route('tienda.home', ['search' => 'leche']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->has('articles.data', 1)
        ->where('articles.data.0.id', $milkArticle->id)
        ->where('filters.search', 'leche')
    );
});

test('category filter includes articles in selected category and child subcategories', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $dairyCategory = Category::factory()->create(['name' => 'Lácteos', 'parent_id' => null, 'is_active' => true]);
    $yogurtCategory = Category::factory()->create(['name' => 'Yogures', 'parent_id' => $dairyCategory->id, 'is_active' => true]);
    $bakeryCategory = Category::factory()->create(['name' => 'Panadería', 'parent_id' => null, 'is_active' => true]);

    $articleInParent = Article::factory()->create([
        'description' => 'Queso Cremoso 1kg',
        'category_id' => $dairyCategory->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    $articleInChild = Article::factory()->create([
        'description' => 'Yogur Firme Vainilla 190g',
        'category_id' => $yogurtCategory->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    $articleInOther = Article::factory()->create([
        'description' => 'Pan Lactal Blanco 500g',
        'category_id' => $bakeryCategory->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    foreach ([$articleInParent, $articleInChild, $articleInOther] as $art) {
        PriceListItem::factory()->create([
            'price_list_id' => $onlineList->id,
            'article_id' => $art->id,
            'price' => '1500.00',
        ]);
    }

    $response = $this->get(route('tienda.home', ['category_id' => $dairyCategory->id]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->has('articles.data', 2)
        ->where('articles.total', 2)
        ->where('filters.category_id', (string) $dairyCategory->id)
    );
});

test('articles in inactive category are not shown', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $inactiveCategory = Category::factory()->create(['name' => 'Descontinuados', 'is_active' => false]);

    $article = Article::factory()->create([
        'description' => 'Artículo en Categoría Inactiva',
        'category_id' => $inactiveCategory->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $article->id,
        'price' => '500.00',
    ]);

    $response = $this->get(route('tienda.home'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->has('articles.data', 0)
        ->where('articles.total', 0)
    );
});

test('pagination divides articles and total excludes unpriced or non-publishable articles', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    // 20 publishable and priced articles
    $articles = Article::factory()->count(20)->create([
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    foreach ($articles as $art) {
        PriceListItem::factory()->create([
            'price_list_id' => $onlineList->id,
            'article_id' => $art->id,
            'price' => '1000.00',
        ]);
    }

    // 5 unpriced articles
    Article::factory()->count(5)->create([
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    // 5 non-publishable articles
    $nonPublishable = Article::factory()->count(5)->create([
        'status' => ArticleStatus::Active,
        'is_online_publishable' => false,
    ]);
    foreach ($nonPublishable as $art) {
        PriceListItem::factory()->create([
            'price_list_id' => $onlineList->id,
            'article_id' => $art->id,
            'price' => '1000.00',
        ]);
    }

    // Page 1
    $responsePage1 = $this->get(route('tienda.home'));
    $responsePage1->assertOk();
    $responsePage1->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->has('articles.data', 16)
        ->where('articles.current_page', 1)
        ->where('articles.last_page', 2)
        ->where('articles.total', 20)
    );

    // Page 2
    $responsePage2 = $this->get(route('tienda.home', ['page' => 2]));
    $responsePage2->assertOk();
    $responsePage2->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->has('articles.data', 4)
        ->where('articles.current_page', 2)
        ->where('articles.total', 20)
    );
});

test('article with image_url provides image_url in catalog props and null when not set', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $articleWithImage = Article::factory()->create([
        'description' => 'Producto Con Imagen',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
        'image_url' => 'https://images.unsplash.com/photo-example.jpg',
    ]);

    $articleWithoutImage = Article::factory()->create([
        'description' => 'Producto Sin Imagen',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
        'image_url' => null,
    ]);

    foreach ([$articleWithImage, $articleWithoutImage] as $art) {
        PriceListItem::factory()->create([
            'price_list_id' => $onlineList->id,
            'article_id' => $art->id,
            'price' => '850.00',
        ]);
    }

    $response = $this->get(route('tienda.home'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->where('articles.data.0.image_url', 'https://images.unsplash.com/photo-example.jpg')
        ->where('articles.data.1.image_url', null)
    );
});

test('catalog categories prop only includes root categories for navigation', function () {
    $rootCatA = Category::factory()->create(['name' => 'Almacén', 'parent_id' => null, 'is_active' => true]);
    $rootCatB = Category::factory()->create(['name' => 'Bebidas', 'parent_id' => null, 'is_active' => true]);
    $childCat = Category::factory()->create(['name' => 'Conservas', 'parent_id' => $rootCatA->id, 'is_active' => true]);

    $response = $this->get(route('tienda.home'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->has('categories', 2)
        ->where('categories.0.name', 'Almacén')
        ->where('categories.1.name', 'Bebidas')
    );
});
