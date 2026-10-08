<?php

use App\Actions\Ecommerce\PlaceWebOrder;
use App\Enums\Catalog\ArticleStatus;
use App\Enums\Ecommerce\DeliveryMethod;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Models\Catalog\Article;
use App\Models\Catalog\Category;
use App\Models\Catalog\UnitOfMeasure;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\CartItem;
use App\Models\Ecommerce\WebOrder;
use App\Models\Organization\Branch;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->onlineList = PriceList::factory()
        ->forChannel(PriceListChannel::Online)
        ->create(['name' => 'Lista Online Principal']);

    $this->category = Category::factory()->create(['is_active' => true]);
    $this->unit = UnitOfMeasure::factory()->create([
        'abbreviation' => 'u',
        'allows_decimal_quantity' => false,
        'is_active' => true,
    ]);

    $this->customer = Customer::factory()->create();
    $this->branch = Branch::factory()->create(['name' => 'Sucursal Centro', 'is_active' => true]);

    $this->action = app(PlaceWebOrder::class);

    $this->addToCart = function (string $description, ?string $price, string $quantity, array $articleAttributes = []): Article {
        $article = Article::factory()->create([
            'description' => $description,
            'category_id' => $this->category->id,
            'unit_of_measure_id' => $this->unit->id,
            'status' => ArticleStatus::Active,
            'is_online_publishable' => true,
            ...$articleAttributes,
        ]);

        if ($price !== null) {
            PriceListItem::factory()->create([
                'price_list_id' => $this->onlineList->id,
                'article_id' => $article->id,
                'price' => $price,
            ]);
        }

        CartItem::factory()->create([
            'customer_id' => $this->customer->id,
            'article_id' => $article->id,
            'quantity' => $quantity,
        ]);

        return $article;
    };
});

test('places a pending pickup order with the prices and origin list of every cart line', function () {
    $water = ($this->addToCart)('Agua Mineral 1.5L', '100.00', '2.000');
    $soda = ($this->addToCart)('Gaseosa Cola 2.25L', '250.50', '3.000');

    $order = $this->action->execute(
        customer: $this->customer,
        deliveryMethod: DeliveryMethod::Pickup,
        pickupBranch: $this->branch,
        notes: 'Retiro después de las 18',
    );

    expect($order->number)->toBe(1)
        ->and($order->customer_id)->toBe($this->customer->id)
        ->and($order->status)->toBe(WebOrderStatus::Pending)
        ->and($order->delivery_method)->toBe(DeliveryMethod::Pickup)
        ->and($order->pickup_branch_id)->toBe($this->branch->id)
        ->and($order->shipping_address)->toBeNull()
        ->and($order->shipping_notes)->toBeNull()
        ->and($order->notes)->toBe('Retiro después de las 18')
        ->and($order->items_amount)->toBe('951.50')
        ->and($order->shipping_cost)->toBe('0.00')
        ->and($order->total_amount)->toBe('951.50')
        ->and($order->placed_at)->not->toBeNull()
        ->and($order->items)->toHaveCount(2);

    $waterLine = $order->items->firstWhere('article_id', $water->id);
    expect($waterLine->quantity)->toBe('2.000')
        ->and($waterLine->unit_price)->toBe('100.00')
        ->and($waterLine->price_list_id)->toBe($this->onlineList->id)
        ->and($waterLine->line_total)->toBe('200.00');

    $sodaLine = $order->items->firstWhere('article_id', $soda->id);
    expect($sodaLine->line_total)->toBe('751.50');
});

test('places a pending delivery order with the configured shipping cost and frozen shipping address', function () {
    config(['ecommerce.shipping_cost' => '2500.00']);

    $water = ($this->addToCart)('Agua Mineral 1.5L', '100.00', '2.000');

    $order = $this->action->execute(
        customer: $this->customer,
        deliveryMethod: DeliveryMethod::Shipping,
        shippingAddress: 'Av. Belgrano 1234, Salta',
        shippingNotes: 'Piso 3 Depto B, timbre blanco',
        notes: 'Llamar antes de entregar',
    );

    expect($order->number)->toBe(1)
        ->and($order->customer_id)->toBe($this->customer->id)
        ->and($order->status)->toBe(WebOrderStatus::Pending)
        ->and($order->delivery_method)->toBe(DeliveryMethod::Shipping)
        ->and($order->pickup_branch_id)->toBeNull()
        ->and($order->shipping_address)->toBe('Av. Belgrano 1234, Salta')
        ->and($order->shipping_notes)->toBe('Piso 3 Depto B, timbre blanco')
        ->and($order->notes)->toBe('Llamar antes de entregar')
        ->and($order->items_amount)->toBe('200.00')
        ->and($order->shipping_cost)->toBe('2500.00')
        ->and($order->total_amount)->toBe('2700.00')
        ->and($order->items)->toHaveCount(1);
});

test('keeps the frozen shipping cost even if the configuration changes later', function () {
    config(['ecommerce.shipping_cost' => '2500.00']);
    ($this->addToCart)('Agua Mineral 1.5L', '100.00', '1.000');

    $order = $this->action->execute(
        customer: $this->customer,
        deliveryMethod: DeliveryMethod::Shipping,
        shippingAddress: 'Av. Belgrano 1234',
    );

    config(['ecommerce.shipping_cost' => '5000.00']);

    expect($order->fresh()->shipping_cost)->toBe('2500.00')
        ->and($order->fresh()->total_amount)->toBe('2600.00');
});

test('does not alter customer account address when a different shipping address is used', function () {
    $this->customer->update(['address' => 'Domicilio Original 111']);
    ($this->addToCart)('Agua Mineral 1.5L', '100.00', '1.000');

    $order = $this->action->execute(
        customer: $this->customer,
        deliveryMethod: DeliveryMethod::Shipping,
        shippingAddress: 'Domicilio Temporal 999',
    );

    expect($order->shipping_address)->toBe('Domicilio Temporal 999')
        ->and($this->customer->fresh()->address)->toBe('Domicilio Original 111');
});

test('rejects a delivery order without shipping address', function () {
    ($this->addToCart)('Agua Mineral 1.5L', '100.00', '1.000');

    expect(fn () => $this->action->execute(
        customer: $this->customer,
        deliveryMethod: DeliveryMethod::Shipping,
        shippingAddress: '   ',
    ))->toThrow(ValidationException::class, 'El domicilio de entrega es obligatorio para envíos a domicilio.');

    expect(WebOrder::count())->toBe(0)
        ->and($this->customer->cartItems()->count())->toBe(1);
});

test('rejects a pickup order without a pickup branch', function () {
    ($this->addToCart)('Agua Mineral 1.5L', '100.00', '1.000');

    expect(fn () => $this->action->execute(
        customer: $this->customer,
        deliveryMethod: DeliveryMethod::Pickup,
        pickupBranch: null,
    ))->toThrow(ValidationException::class, 'La sucursal de retiro elegida no está activa.');

    expect(WebOrder::count())->toBe(0);
});

test('empties the cart once the order is placed', function () {
    ($this->addToCart)('Agua Mineral 1.5L', '100.00', '1.000');

    $this->action->execute($this->customer, DeliveryMethod::Pickup, $this->branch);

    expect($this->customer->cartItems()->count())->toBe(0);
});

test('rejects an empty cart', function () {
    expect(fn () => $this->action->execute($this->customer, DeliveryMethod::Pickup, $this->branch))
        ->toThrow(ValidationException::class, 'Tu carrito está vacío');

    expect(WebOrder::count())->toBe(0);
});

test('rejects the order naming every unavailable article and leaves the cart untouched', function () {
    ($this->addToCart)('Agua Mineral 1.5L', '100.00', '1.000');
    ($this->addToCart)('Yerba Mate 1kg', null, '1.000');
    ($this->addToCart)('Fideos Tirabuzón', '80.00', '2.000', ['is_online_publishable' => false]);

    try {
        $this->action->execute($this->customer, DeliveryMethod::Pickup, $this->branch);
        $this->fail('The order should have been rejected.');
    } catch (ValidationException $exception) {
        $message = $exception->errors()['cart'][0];

        expect($message)->toContain('Yerba Mate 1kg (sin precio vigente)')
            ->and($message)->toContain('Fideos Tirabuzón (no disponible para venta online)')
            ->and($message)->not->toContain('Agua Mineral');
    }

    expect(WebOrder::count())->toBe(0)
        ->and($this->customer->cartItems()->count())->toBe(3);
});

test('rejects an inactive pickup branch', function () {
    ($this->addToCart)('Agua Mineral 1.5L', '100.00', '1.000');
    $closedBranch = Branch::factory()->create(['is_active' => false]);

    expect(fn () => $this->action->execute($this->customer, DeliveryMethod::Pickup, $closedBranch))
        ->toThrow(ValidationException::class, 'La sucursal de retiro elegida no está activa.');

    expect(WebOrder::count())->toBe(0)
        ->and($this->customer->cartItems()->count())->toBe(1);
});

test('numbers the orders correlatively across customers', function () {
    WebOrder::factory()->create(['number' => 41]);

    ($this->addToCart)('Agua Mineral 1.5L', '100.00', '1.000');
    $first = $this->action->execute($this->customer, DeliveryMethod::Pickup, $this->branch);

    ($this->addToCart)('Gaseosa Cola 2.25L', '250.00', '1.000');
    $second = $this->action->execute($this->customer, DeliveryMethod::Pickup, $this->branch);

    expect($first->number)->toBe(42)
        ->and($second->number)->toBe(43)
        ->and($second->formattedNumber())->toBe('00000043');
});

test('keeps the original price after the online list changes', function () {
    $water = ($this->addToCart)('Agua Mineral 1.5L', '100.00', '2.000');

    $order = $this->action->execute($this->customer, DeliveryMethod::Pickup, $this->branch);

    PriceListItem::query()
        ->where('price_list_id', $this->onlineList->id)
        ->where('article_id', $water->id)
        ->update(['price' => '180.00']);

    $line = $order->fresh()->items->sole();

    expect($line->unit_price)->toBe('100.00')
        ->and($line->line_total)->toBe('200.00')
        ->and($order->fresh()->total_amount)->toBe('200.00');
});

test('freezes the customer particular list price when one applies', function () {
    $particular = PriceList::factory()->particular()->create(['name' => 'Lista Rotisería']);
    $this->customer->update(['price_list_id' => $particular->id]);

    $water = ($this->addToCart)('Agua Mineral 1.5L', '100.00', '1.000');
    PriceListItem::factory()->create([
        'price_list_id' => $particular->id,
        'article_id' => $water->id,
        'price' => '90.00',
    ]);

    $line = $this->action->execute($this->customer->fresh(), DeliveryMethod::Pickup, $this->branch)->items->sole();

    expect($line->unit_price)->toBe('90.00')
        ->and($line->price_list_id)->toBe($particular->id);
});
