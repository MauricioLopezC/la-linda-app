<?php

use App\Actions\Ecommerce\ConsultOnlineCatalog;
use App\Actions\Ecommerce\GetOnlineAvailability;
use App\Enums\Catalog\ArticleStatus;
use App\Enums\Ecommerce\OnlineAvailabilityStatus;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Models\Catalog\Article;
use App\Models\Catalog\Category;
use App\Models\Catalog\UnitOfMeasure;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;

beforeEach(function () {
    $this->branch = Branch::factory()->create(['name' => 'Sucursal Central', 'is_active' => true]);
    $this->onlineWarehouse = Warehouse::factory()->online()->create([
        'name' => 'Depósito Online',
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);

    $this->category = Category::factory()->create(['name' => 'Almacén', 'is_active' => true]);
    $this->unit = UnitOfMeasure::factory()->create([
        'name' => 'Unidad',
        'abbreviation' => 'u',
        'allows_decimal_quantity' => false,
        'is_active' => true,
    ]);

    $this->onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create(['name' => 'Lista Online']);

    $this->customer = Customer::factory()->create();
    $this->action = app(GetOnlineAvailability::class);
});

test('calculates available quantity by subtracting paid and in preparation orders from physical balance', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    // Physical stock: 10 units
    StockBalance::factory()->create([
        'warehouse_id' => $this->onlineWarehouse->id,
        'article_id' => $article->id,
        'quantity' => '10.000',
    ]);

    // Paid order with 2 units
    $paidOrder = WebOrder::factory()->paid()->create(['customer_id' => $this->customer->id]);
    WebOrderItem::factory()->create([
        'web_order_id' => $paidOrder->id,
        'article_id' => $article->id,
        'quantity' => '2.000',
    ]);

    // In-preparation order with 1 unit
    $prepOrder = WebOrder::factory()->inPreparation()->create(['customer_id' => $this->customer->id]);
    WebOrderItem::factory()->create([
        'web_order_id' => $prepOrder->id,
        'article_id' => $article->id,
        'quantity' => '1.000',
    ]);

    // Pending order with 3 units (MUST NOT discount from available quantity)
    $pendingOrder = WebOrder::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => WebOrderStatus::Pending,
    ]);
    WebOrderItem::factory()->create([
        'web_order_id' => $pendingOrder->id,
        'article_id' => $article->id,
        'quantity' => '3.000',
    ]);

    // Delivered order with 4 units (MUST NOT count as committed because stock was already physically deducted)
    $deliveredOrder = WebOrder::factory()->delivered()->create(['customer_id' => $this->customer->id]);
    WebOrderItem::factory()->create([
        'web_order_id' => $deliveredOrder->id,
        'article_id' => $article->id,
        'quantity' => '4.000',
    ]);

    // Available: 10 - (2 + 1) = 7.000
    $result = $this->action->forArticle($article);

    expect($result->availableQuantity)->toBe('7.000')
        ->and($result->numericQuantity)->toBe(7.0)
        ->and($result->status)->toBe(OnlineAvailabilityStatus::Available)
        ->and($result->isAvailable)->toBeTrue();
});

test('determines status as LastUnits when available quantity is within threshold', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    // Exactly 5 units (default threshold is 5.0)
    StockBalance::factory()->create([
        'warehouse_id' => $this->onlineWarehouse->id,
        'article_id' => $article->id,
        'quantity' => '5.000',
    ]);

    $result = $this->action->forArticle($article);

    expect($result->availableQuantity)->toBe('5.000')
        ->and($result->status)->toBe(OnlineAvailabilityStatus::LastUnits)
        ->and($result->status->label())->toBe('Últimas unidades')
        ->and($result->isAvailable)->toBeTrue();
});

test('determines status as OutOfStock when available quantity is zero or exhausted by committed orders', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    StockBalance::factory()->create([
        'warehouse_id' => $this->onlineWarehouse->id,
        'article_id' => $article->id,
        'quantity' => '2.000',
    ]);

    // Committed: 2 units in paid order
    $paidOrder = WebOrder::factory()->paid()->create(['customer_id' => $this->customer->id]);
    WebOrderItem::factory()->create([
        'web_order_id' => $paidOrder->id,
        'article_id' => $article->id,
        'quantity' => '2.000',
    ]);

    $result = $this->action->forArticle($article);

    expect($result->availableQuantity)->toBe('0.000')
        ->and($result->numericQuantity)->toBe(0.0)
        ->and($result->status)->toBe(OnlineAvailabilityStatus::OutOfStock)
        ->and($result->status->label())->toBe('Sin stock')
        ->and($result->isAvailable)->toBeFalse();
});

test('returns OutOfStock with zero quantity when no online warehouse is active', function () {
    $this->onlineWarehouse->update(['is_active' => false]);

    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    $result = $this->action->forArticle($article);

    expect($result->availableQuantity)->toBe('0.000')
        ->and($result->status)->toBe(OnlineAvailabilityStatus::OutOfStock)
        ->and($result->isAvailable)->toBeFalse();
});

test('batch calculation for multiple articles maps availability correctly without N+1', function () {
    $article1 = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);
    $article2 = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    StockBalance::factory()->create([
        'warehouse_id' => $this->onlineWarehouse->id,
        'article_id' => $article1->id,
        'quantity' => '10.000',
    ]);
    StockBalance::factory()->create([
        'warehouse_id' => $this->onlineWarehouse->id,
        'article_id' => $article2->id,
        'quantity' => '2.000',
    ]);

    $results = $this->action->forArticles([$article1, $article2]);

    expect($results)->toHaveCount(2)
        ->and($results[$article1->id]->availableQuantity)->toBe('10.000')
        ->and($results[$article1->id]->status)->toBe(OnlineAvailabilityStatus::Available)
        ->and($results[$article2->id]->availableQuantity)->toBe('2.000')
        ->and($results[$article2->id]->status)->toBe(OnlineAvailabilityStatus::LastUnits);
});

test('online catalog includes available_quantity and availability_status on every article', function () {
    $article = Article::factory()->create([
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'status' => ArticleStatus::Active,
        'is_online_publishable' => true,
    ]);

    PriceListItem::factory()->create([
        'price_list_id' => $this->onlineList->id,
        'article_id' => $article->id,
        'price' => '150.00',
    ]);

    StockBalance::factory()->create([
        'warehouse_id' => $this->onlineWarehouse->id,
        'article_id' => $article->id,
        'quantity' => '4.000',
    ]);

    $catalogAction = app(ConsultOnlineCatalog::class);
    $paginator = $catalogAction->execute([], $this->customer);

    expect($paginator->items())->toHaveCount(1);
    $catalogItem = $paginator->items()[0];

    expect($catalogItem->available_quantity)->toBe('4.000')
        ->and($catalogItem->availability_status)->toBe('ultimas_unidades')
        ->and($catalogItem->availability_label)->toBe('Últimas unidades');
});
