<?php

use App\Actions\Ecommerce\AddArticleToCart;
use App\Actions\Ecommerce\GetCustomerCart;
use App\Actions\Ecommerce\GetOnlineAvailability;
use App\Actions\Ecommerce\PlaceWebOrder;
use App\Actions\Ecommerce\UpdateCartItemQuantity;
use App\Enums\Catalog\ArticleStatus;
use App\Enums\Ecommerce\DeliveryMethod;
use App\Enums\Ecommerce\OnlineAvailabilityStatus;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Models\Catalog\Article;
use App\Models\Catalog\Category;
use App\Models\Catalog\UnitOfMeasure;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->branch = Branch::factory()->create(['name' => 'Sucursal Centro', 'is_active' => true]);
    $this->onlineWarehouse = Warehouse::factory()->online()->create([
        'name' => 'Depósito Online',
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);

    $this->category = Category::factory()->create(['name' => 'Bebidas', 'is_active' => true]);
    $this->unit = UnitOfMeasure::factory()->create([
        'name' => 'Unidad',
        'abbreviation' => 'u',
        'allows_decimal_quantity' => false,
        'is_active' => true,
    ]);

    $this->onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create(['name' => 'Lista Online Principal']);

    $this->customerA = Customer::factory()->create();
    $this->customerB = Customer::factory()->create();

    $this->addArticleToCart = app(AddArticleToCart::class);
    $this->updateCartItem = app(UpdateCartItemQuantity::class);
    $this->getCustomerCart = app(GetCustomerCart::class);
    $this->placeWebOrder = app(PlaceWebOrder::class);

    $this->createPricedArticle = function (string $description, string $price, string $stock): Article {
        $article = Article::factory()->create([
            'description' => $description,
            'category_id' => $this->category->id,
            'unit_of_measure_id' => $this->unit->id,
            'status' => ArticleStatus::Active,
            'is_online_publishable' => true,
        ]);

        PriceListItem::factory()->create([
            'price_list_id' => $this->onlineList->id,
            'article_id' => $article->id,
            'price' => $price,
        ]);

        StockBalance::factory()->create([
            'warehouse_id' => $this->onlineWarehouse->id,
            'article_id' => $article->id,
            'quantity' => $stock,
        ]);

        return $article;
    };
});

test('cannot add article to cart when it has no available stock', function () {
    $article = ($this->createPricedArticle)('Aceite de Oliva 500ml', '1200.00', '0.000');

    expect(fn () => $this->addArticleToCart->execute($this->customerA, $article, 1))
        ->toThrow(ValidationException::class, 'El artículo no se encuentra disponible por falta de stock.');
});

test('cannot add quantity exceeding available online stock', function () {
    $article = ($this->createPricedArticle)('Café Tostado 250g', '850.00', '3.000');

    expect(fn () => $this->addArticleToCart->execute($this->customerA, $article, 4))
        ->toThrow(ValidationException::class, 'La cantidad solicitada (4) supera el stock disponible en la tienda online (3.000).');
});

test('cannot accumulate cart quantity beyond available online stock', function () {
    $article = ($this->createPricedArticle)('Galletitas Dulces', '300.00', '4.000');

    // Add 2 units
    $this->addArticleToCart->execute($this->customerA, $article, 2);

    // Attempting to add 3 more (total 5 > 4 available) must fail
    expect(fn () => $this->addArticleToCart->execute($this->customerA, $article, 3))
        ->toThrow(ValidationException::class, 'La cantidad total solicitada (5) supera el stock disponible en la tienda online (4.000).');
});

test('cannot update cart item quantity beyond available online stock', function () {
    $article = ($this->createPricedArticle)('Detergente 750ml', '400.00', '5.000');

    $cartItem = $this->addArticleToCart->execute($this->customerA, $article, 2);

    expect(fn () => $this->updateCartItem->execute($this->customerA, $cartItem, 6))
        ->toThrow(ValidationException::class, 'La cantidad solicitada (6) supera el stock disponible en la tienda online (5.000).');
});

test('customer cart marks item as unavailable with explicit reason when stock is depleted or insufficient', function () {
    $article1 = ($this->createPricedArticle)('Azúcar 1kg', '600.00', '5.000');
    $article2 = ($this->createPricedArticle)('Harina 000 1kg', '450.00', '2.000');

    // Add to cart while stock was sufficient
    $this->addArticleToCart->execute($this->customerA, $article1, 2);
    $this->addArticleToCart->execute($this->customerA, $article2, 2);

    // Another customer buys and commits the entire stock of article2 and partial stock of article1
    $order = WebOrder::factory()->paid()->create(['customer_id' => $this->customerB->id]);
    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $article2->id,
        'quantity' => '2.000', // Exhausts article2 stock (available: 0.000)
    ]);
    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $article1->id,
        'quantity' => '4.000', // Leaves 1.000 available, but customerA wanted 2
    ]);

    $cart = $this->getCustomerCart->execute($this->customerA);

    expect($cart->has_unavailable_items)->toBeTrue();

    $item1 = collect($cart->items)->firstWhere('article_id', $article1->id);
    expect($item1->is_available)->toBeFalse()
        ->and($item1->unavailable_reason)->toContain('Stock insuficiente (disponible: 1.000 u)');

    $item2 = collect($cart->items)->firstWhere('article_id', $article2->id);
    expect($item2->is_available)->toBeFalse()
        ->and($item2->unavailable_reason)->toBe('Sin stock disponible');
});

test('verification scenario: Client A buys 2 units of 3, Client B enters store, sees 1 unit and fails if attempting to place order for 2', function () {
    // 1. Un artículo tiene 3 unidades en el depósito online
    $article = ($this->createPricedArticle)('Queso Cremoso 1kg', '1500.00', '3.000');

    // 2. Cliente A compra y agrega 2 unidades al carrito
    $this->addArticleToCart->execute($this->customerA, $article, 2);

    // 3. Cliente A confirma el pedido y queda pagado (aún sin entregarse en depósito)
    $orderA = $this->placeWebOrder->execute(
        customer: $this->customerA,
        deliveryMethod: DeliveryMethod::Pickup,
        pickupBranch: $this->branch,
    );
    $orderA->update([
        'status' => WebOrderStatus::Paid,
        'paid_at' => now(),
    ]);

    // 4. Cliente B entra a la tienda y consulta la disponibilidad real: solo queda 1 unidad disponible
    $availability = app(GetOnlineAvailability::class)->forArticle($article);
    expect($availability->availableQuantity)->toBe('1.000')
        ->and($availability->status)->toBe(OnlineAvailabilityStatus::LastUnits);

    // 5. Si Cliente B intenta agregar 2 unidades al carrito, es rechazado
    expect(fn () => $this->addArticleToCart->execute($this->customerB, $article, 2))
        ->toThrow(ValidationException::class, 'supera el stock disponible en la tienda online (1.000)');

    // 6. Si Cliente B ya tenía 2 unidades cargadas antes del pago de Cliente A e intenta confirmar checkout:
    CartItem::factory()->create([
        'customer_id' => $this->customerB->id,
        'article_id' => $article->id,
        'quantity' => '2.000',
    ]);

    expect(fn () => $this->placeWebOrder->execute(
        customer: $this->customerB,
        deliveryMethod: DeliveryMethod::Pickup,
        pickupBranch: $this->branch,
    ))->toThrow(
        ValidationException::class,
        'No se puede confirmar el pedido porque no hay stock suficiente para: Queso Cremoso 1kg (solicitado: 2.000, disponible: 1.000).'
    );
});
