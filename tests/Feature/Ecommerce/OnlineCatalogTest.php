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

test('search by description is accent-insensitive and case-insensitive', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $sugarArticle = Article::factory()->create([
        'description' => 'Azúcar Blanco Ledesma 1kg',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    $coffeeArticle = Article::factory()->create([
        'description' => 'Café Molido La Virginia 500g',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $sugarArticle->id,
        'price' => '1200.00',
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $coffeeArticle->id,
        'price' => '3500.00',
    ]);

    // Search without accents for an accented word in DB
    $resWithoutAccent = $this->get(route('tienda.home', ['search' => 'azucar']));
    $resWithoutAccent->assertOk();
    $resWithoutAccent->assertInertia(fn (Assert $page) => $page
        ->has('articles.data', 1)
        ->where('articles.data.0.id', $sugarArticle->id)
    );

    // Search with uppercase and accents
    $resUppercase = $this->get(route('tienda.home', ['search' => 'AZÚCAR']));
    $resUppercase->assertOk();
    $resUppercase->assertInertia(fn (Assert $page) => $page
        ->has('articles.data', 1)
        ->where('articles.data.0.id', $sugarArticle->id)
    );

    // Search without accents for café
    $resCoffee = $this->get(route('tienda.home', ['search' => 'cafe']));
    $resCoffee->assertOk();
    $resCoffee->assertInertia(fn (Assert $page) => $page
        ->has('articles.data', 1)
        ->where('articles.data.0.id', $coffeeArticle->id)
    );
});

test('when search or category filter yields no articles, suggested articles are returned', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $milkArticle = Article::factory()->create([
        'description' => 'Leche Entera 1L',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    $breadArticle = Article::factory()->create([
        'description' => 'Pan Blanco 500g',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    foreach ([$milkArticle, $breadArticle] as $art) {
        PriceListItem::factory()->create([
            'price_list_id' => $onlineList->id,
            'article_id' => $art->id,
            'price' => '1000.00',
        ]);
    }

    $response = $this->get(route('tienda.home', ['search' => 'articulo-inexistente-xyz']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->has('articles.data', 0)
        ->where('articles.total', 0)
        ->has('suggestedArticles', 2)
    );
});

test('when articles are found in catalog, suggested articles is empty', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $article = Article::factory()->create([
        'description' => 'Arroz Blanco 1kg',
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $article->id,
        'price' => '1200.00',
    ]);

    $response = $this->get(route('tienda.home'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->has('articles.data', 1)
        ->has('suggestedArticles', 0)
    );
});

test('catalog can be filtered by active brand', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $category = Category::factory()->create(['is_active' => true]);
    $brandA = Brand::factory()->create(['name' => 'Arcor', 'is_active' => true]);
    $brandB = Brand::factory()->create(['name' => 'Marolio', 'is_active' => true]);

    $articleA = Article::factory()->create([
        'description' => 'Mermelada Arcor',
        'category_id' => $category->id,
        'brand_id' => $brandA->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    $articleB = Article::factory()->create([
        'description' => 'Tomate Marolio',
        'category_id' => $category->id,
        'brand_id' => $brandB->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $articleA->id,
        'price' => '1000.00',
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $articleB->id,
        'price' => '800.00',
    ]);

    $response = $this->get(route('tienda.home', ['brand_id' => $brandA->id]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->has('articles.data', 1)
        ->where('articles.data.0.id', $articleA->id)
        ->where('articles.total', 1)
        ->where('filters.brand_id', (string) $brandA->id)
    );
});

test('inactive brands or brands without online publishable articles are not included in available brands list', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $category = Category::factory()->create(['is_active' => true]);

    // Active brand with online publishable priced article
    $brandValid = Brand::factory()->create(['name' => 'Marca Valida', 'is_active' => true]);
    $articleValid = Article::factory()->create([
        'category_id' => $category->id,
        'brand_id' => $brandValid->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $articleValid->id,
        'price' => '500.00',
    ]);

    // Inactive brand with article
    $brandInactive = Brand::factory()->create(['name' => 'Marca Inactiva', 'is_active' => false]);
    $articleInactiveBrand = Article::factory()->create([
        'category_id' => $category->id,
        'brand_id' => $brandInactive->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $articleInactiveBrand->id,
        'price' => '500.00',
    ]);

    // Active brand but article not online publishable
    $brandNotPublishable = Brand::factory()->create(['name' => 'Marca No Publicable', 'is_active' => true]);
    $articleNotPublishable = Article::factory()->create([
        'category_id' => $category->id,
        'brand_id' => $brandNotPublishable->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => false,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $articleNotPublishable->id,
        'price' => '500.00',
    ]);

    // Active brand with publishable article but without price
    $brandNoPrice = Brand::factory()->create(['name' => 'Marca Sin Precio', 'is_active' => true]);
    Article::factory()->create([
        'category_id' => $category->id,
        'brand_id' => $brandNoPrice->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    $response = $this->get(route('tienda.home'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->has('brands', 1)
        ->where('brands.0.id', $brandValid->id)
        ->where('brands.0.name', 'Marca Valida')
    );
});

test('catalog can be filtered by price range for guest visitor', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $category = Category::factory()->create(['is_active' => true]);

    $cheap = Article::factory()->create([
        'description' => 'Articulo Barato',
        'category_id' => $category->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $cheap->id,
        'price' => '500.00',
    ]);

    $medium = Article::factory()->create([
        'description' => 'Articulo Medio',
        'category_id' => $category->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $medium->id,
        'price' => '1500.00',
    ]);

    $expensive = Article::factory()->create([
        'description' => 'Articulo Caro',
        'category_id' => $category->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $expensive->id,
        'price' => '3000.00',
    ]);

    // Range 1000 - 2000 -> only medium
    $resRange = $this->get(route('tienda.home', ['min_price' => '1000', 'max_price' => '2000']));
    $resRange->assertOk();
    $resRange->assertInertia(fn (Assert $page) => $page
        ->has('articles.data', 1)
        ->where('articles.data.0.id', $medium->id)
        ->where('articles.total', 1)
        ->where('filters.min_price', '1000')
        ->where('filters.max_price', '2000')
    );

    // Min 2000 -> only expensive
    $resMin = $this->get(route('tienda.home', ['min_price' => '2000']));
    $resMin->assertOk();
    $resMin->assertInertia(fn (Assert $page) => $page
        ->has('articles.data', 1)
        ->where('articles.data.0.id', $expensive->id)
    );

    // Max 1000 -> only cheap
    $resMax = $this->get(route('tienda.home', ['max_price' => '1000']));
    $resMax->assertOk();
    $resMax->assertInertia(fn (Assert $page) => $page
        ->has('articles.data', 1)
        ->where('articles.data.0.id', $cheap->id)
    );
});

test('catalog can be filtered by price range for logged in customer with particular price list', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $particularList = PriceList::factory()
        ->create([
            'scope' => PriceListScope::Particular,
            'channel' => null,
            'is_active' => true,
        ]);

    $customer = Customer::factory()->create([
        'price_list_id' => $particularList->id,
    ]);
    $user = User::factory()->create([
        'customer_id' => $customer->id,
    ]);

    $category = Category::factory()->create(['is_active' => true]);

    $articleA = Article::factory()->create([
        'description' => 'Articulo Con Descuento',
        'category_id' => $category->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $articleA->id,
        'price' => '2500.00',
    ]);
    // Particular price for customer gives 1200.00
    PriceListItem::factory()->create([
        'price_list_id' => $particularList->id,
        'article_id' => $articleA->id,
        'price' => '1200.00',
    ]);

    $articleB = Article::factory()->create([
        'description' => 'Articulo Sin Descuento',
        'category_id' => $category->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $articleB->id,
        'price' => '2500.00',
    ]);

    // As guest, both are 2500, so min_price 1000 & max_price 1500 returns 0 articles
    $guestRes = $this->get(route('tienda.home', ['min_price' => '1000', 'max_price' => '1500']));
    $guestRes->assertOk();
    $guestRes->assertInertia(fn (Assert $page) => $page->has('articles.data', 0));

    // As logged in customer, articleA is 1200 (in range), articleB is 2500 (out of range)
    $authRes = $this->actingAs($user)->get(route('tienda.home', ['min_price' => '1000', 'max_price' => '1500']));
    $authRes->assertOk();
    $authRes->assertInertia(fn (Assert $page) => $page
        ->has('articles.data', 1)
        ->where('articles.data.0.id', $articleA->id)
        ->where('articles.data.0.price', '1200.00')
        ->where('articles.data.0.is_particular_price', true)
    );
});

test('catalog can be ordered by price ascending and descending', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $category = Category::factory()->create(['is_active' => true]);

    $art300 = Article::factory()->create([
        'description' => 'Articulo Trescientos',
        'category_id' => $category->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $art300->id,
        'price' => '300.00',
    ]);

    $art100 = Article::factory()->create([
        'description' => 'Articulo Cien',
        'category_id' => $category->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $art100->id,
        'price' => '100.00',
    ]);

    $art200 = Article::factory()->create([
        'description' => 'Articulo Doscientos',
        'category_id' => $category->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $art200->id,
        'price' => '200.00',
    ]);

    // price_asc: 100, 200, 300
    $resAsc = $this->get(route('tienda.home', ['sort' => 'price_asc']));
    $resAsc->assertOk();
    $resAsc->assertInertia(fn (Assert $page) => $page
        ->has('articles.data', 3)
        ->where('articles.data.0.id', $art100->id)
        ->where('articles.data.1.id', $art200->id)
        ->where('articles.data.2.id', $art300->id)
    );

    // price_desc: 300, 200, 100
    $resDesc = $this->get(route('tienda.home', ['sort' => 'price_desc']));
    $resDesc->assertOk();
    $resDesc->assertInertia(fn (Assert $page) => $page
        ->has('articles.data', 3)
        ->where('articles.data.0.id', $art300->id)
        ->where('articles.data.1.id', $art200->id)
        ->where('articles.data.2.id', $art100->id)
    );
});

test('catalog can be ordered by name alphabetically A-Z and Z-A', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $category = Category::factory()->create(['is_active' => true]);

    $artZ = Article::factory()->create([
        'description' => 'Zanahorias',
        'category_id' => $category->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $artZ->id,
        'price' => '500.00',
    ]);

    $artA = Article::factory()->create([
        'description' => 'Acelga',
        'category_id' => $category->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $artA->id,
        'price' => '500.00',
    ]);

    $artM = Article::factory()->create([
        'description' => 'Manzanas',
        'category_id' => $category->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $artM->id,
        'price' => '500.00',
    ]);

    // name_asc: Acelga, Manzanas, Zanahorias
    $resAsc = $this->get(route('tienda.home', ['sort' => 'name_asc']));
    $resAsc->assertOk();
    $resAsc->assertInertia(fn (Assert $page) => $page
        ->has('articles.data', 3)
        ->where('articles.data.0.id', $artA->id)
        ->where('articles.data.1.id', $artM->id)
        ->where('articles.data.2.id', $artZ->id)
    );

    // name_desc: Zanahorias, Manzanas, Acelga
    $resDesc = $this->get(route('tienda.home', ['sort' => 'name_desc']));
    $resDesc->assertOk();
    $resDesc->assertInertia(fn (Assert $page) => $page
        ->has('articles.data', 3)
        ->where('articles.data.0.id', $artZ->id)
        ->where('articles.data.1.id', $artM->id)
        ->where('articles.data.2.id', $artA->id)
    );
});

test('all filters and sorting can be combined simultaneously', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $catDairy = Category::factory()->create(['name' => 'Lácteos', 'is_active' => true]);

    $brandSerenisima = Brand::factory()->create(['name' => 'La Serenísima', 'is_active' => true]);
    $brandIlolay = Brand::factory()->create(['name' => 'Ilolay', 'is_active' => true]);

    // Target match: Lácteos, La Serenísima, "Leche Entera", $1200
    $target1 = Article::factory()->create([
        'description' => 'Leche Entera Clásica 1L',
        'category_id' => $catDairy->id,
        'brand_id' => $brandSerenisima->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $target1->id,
        'price' => '1200.00',
    ]);

    // Target match: Lácteos, La Serenísima, "Leche Descremada", $1100
    $target2 = Article::factory()->create([
        'description' => 'Leche Descremada Liviana 1L',
        'category_id' => $catDairy->id,
        'brand_id' => $brandSerenisima->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $target2->id,
        'price' => '1100.00',
    ]);

    // Non-match: Different brand
    $otherBrand = Article::factory()->create([
        'description' => 'Leche Entera Ilolay',
        'category_id' => $catDairy->id,
        'brand_id' => $brandIlolay->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $otherBrand->id,
        'price' => '1150.00',
    ]);

    // Non-match: Out of price range ($2500)
    $expensive = Article::factory()->create([
        'description' => 'Leche Especial Proteica 1L',
        'category_id' => $catDairy->id,
        'brand_id' => $brandSerenisima->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $expensive->id,
        'price' => '2500.00',
    ]);

    // Combined query: search 'leche', catDairy, brandSerenisima, min 1000, max 2000, sort price_asc
    $response = $this->get(route('tienda.home', [
        'search' => 'leche',
        'category_id' => $catDairy->id,
        'brand_id' => $brandSerenisima->id,
        'min_price' => '1000',
        'max_price' => '2000',
        'sort' => 'price_asc',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('articles.data', 2)
        ->where('articles.data.0.id', $target2->id) // 1100.00
        ->where('articles.data.1.id', $target1->id) // 1200.00
        ->where('articles.total', 2)
    );
});

test('catalog filters validate parameters correctly', function () {
    // Negative min_price
    $this->get(route('tienda.home', ['min_price' => '-100']))
        ->assertSessionHasErrors('min_price');

    // Negative max_price
    $this->get(route('tienda.home', ['max_price' => '-50']))
        ->assertSessionHasErrors('max_price');

    // max_price < min_price
    $this->get(route('tienda.home', ['min_price' => '500', 'max_price' => '400']))
        ->assertSessionHasErrors('max_price');

    // Non-existent brand_id
    $this->get(route('tienda.home', ['brand_id' => 99999]))
        ->assertSessionHasErrors('brand_id');

    // Inactive brand_id
    $inactiveBrand = Brand::factory()->create(['is_active' => false]);
    $this->get(route('tienda.home', ['brand_id' => $inactiveBrand->id]))
        ->assertSessionHasErrors('brand_id');

    // Invalid sort option
    $this->get(route('tienda.home', ['sort' => 'invalid_order']))
        ->assertSessionHasErrors('sort');
});

test('when brand filter yields no articles, suggested articles are returned', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $category = Category::factory()->create(['is_active' => true]);
    $brandA = Brand::factory()->create(['name' => 'Marca Con Articulos', 'is_active' => true]);
    $brandB = Brand::factory()->create(['name' => 'Marca Sin Articulos Filtrados', 'is_active' => true]);

    $articleA = Article::factory()->create([
        'category_id' => $category->id,
        'brand_id' => $brandA->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $articleA->id,
        'price' => '1000.00',
    ]);

    // Give brandB an article that will be excluded by price
    $articleB = Article::factory()->create([
        'category_id' => $category->id,
        'brand_id' => $brandB->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $articleB->id,
        'price' => '5000.00',
    ]);

    // Filter brandB with max_price 1000 -> 0 matches
    $response = $this->get(route('tienda.home', [
        'brand_id' => $brandB->id,
        'max_price' => '1000',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->has('articles.data', 0)
        ->has('suggestedArticles')
    );
});

test('active subcategory absent from root categories is resolved and returned in activeCategory prop', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $parentCategory = Category::factory()->create([
        'name' => 'Lácteos Raíz',
        'parent_id' => null,
        'is_active' => true,
    ]);

    $subCategory = Category::factory()->create([
        'name' => 'Yogures Subcategoría',
        'parent_id' => $parentCategory->id,
        'is_active' => true,
    ]);

    $article = Article::factory()->create([
        'description' => 'Yogur de Fresa',
        'category_id' => $subCategory->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $article->id,
        'price' => '1200.00',
    ]);

    $response = $this->get(route('tienda.home', [
        'category_id' => $subCategory->id,
        'sort' => 'price_asc',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->has('articles.data', 1)
        ->where('articles.data.0.id', $article->id)
        ->where('activeCategory.id', $subCategory->id)
        ->where('activeCategory.name', 'Yogures Subcategoría')
        ->where('filters.category_id', (string) $subCategory->id)
        ->where('filters.sort', 'price_asc')
        ->where('categories', fn ($cats) => ! collect($cats)->contains('id', $subCategory->id))
    );
});

test('active brand without online articles is absent from brands options but resolved in activeBrand prop', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $category = Category::factory()->create(['is_active' => true]);
    $brandWithItems = Brand::factory()->create(['name' => 'Marca Con Stock', 'is_active' => true]);
    $brandEmpty = Brand::factory()->create(['name' => 'Marca Sin Articulos', 'is_active' => true]);

    $article = Article::factory()->create([
        'category_id' => $category->id,
        'brand_id' => $brandWithItems->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $article->id,
        'price' => '2000.00',
    ]);

    $response = $this->get(route('tienda.home', [
        'brand_id' => $brandEmpty->id,
        'search' => 'producto',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ecommerce/index')
        ->where('activeBrand.id', $brandEmpty->id)
        ->where('activeBrand.name', 'Marca Sin Articulos')
        ->where('filters.brand_id', (string) $brandEmpty->id)
        ->where('filters.search', 'producto')
        ->where('brands', fn ($b) => ! collect($b)->contains('id', $brandEmpty->id))
    );
});

test('invalid price range returns validation error for max_price', function () {
    $response = $this->get(route('tienda.home', [
        'min_price' => '500',
        'max_price' => '400',
    ]));

    $response->assertSessionHasErrors([
        'max_price' => 'El precio máximo debe ser mayor o igual al precio mínimo.',
    ]);
});

test('removing category filter retains remaining filters and query params', function () {
    $onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create();

    $category = Category::factory()->create(['is_active' => true]);
    $brand = Brand::factory()->create(['is_active' => true]);

    $article = Article::factory()->create([
        'description' => 'Arroz Blanco',
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $onlineList->id,
        'article_id' => $article->id,
        'price' => '800.00',
    ]);

    // Initial query with category, brand, and sort
    $initialResponse = $this->get(route('tienda.home', [
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'sort' => 'name_asc',
    ]));
    $initialResponse->assertOk();
    $initialResponse->assertInertia(fn (Assert $page) => $page
        ->where('filters.category_id', (string) $category->id)
        ->where('filters.brand_id', (string) $brand->id)
        ->where('filters.sort', 'name_asc')
    );

    // Simulating removing the category chip (setting category_id to 'all' or omitting)
    $afterRemovalResponse = $this->get(route('tienda.home', [
        'category_id' => 'all',
        'brand_id' => $brand->id,
        'sort' => 'name_asc',
    ]));
    $afterRemovalResponse->assertOk();
    $afterRemovalResponse->assertInertia(fn (Assert $page) => $page
        ->where('filters.category_id', 'all')
        ->where('filters.brand_id', (string) $brand->id)
        ->where('filters.sort', 'name_asc')
        ->where('articles.total', 1)
    );
});
