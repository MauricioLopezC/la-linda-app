<?php

use App\Actions\Ecommerce\AddArticleToCart;
use App\Actions\Ecommerce\GetCustomerCart;
use App\Actions\Ecommerce\GetOnlineAvailability;
use App\Actions\Ecommerce\PlaceWebOrder;
use App\Actions\Ecommerce\UpdateCartItemQuantity;
use App\Enums\Ecommerce\DeliveryMethod;
use App\Enums\Pricing\PriceListChannel;
use App\Models\Catalog\Article;
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
    $this->branch = Branch::factory()->create();
    $this->warehouse = Warehouse::factory()->online()->create(['branch_id' => $this->branch->id]);
    $this->customer = Customer::factory()->create();
    $this->list = PriceList::factory()->forChannel(PriceListChannel::Online)->create();
    $unit = UnitOfMeasure::factory()->create(['allows_decimal_quantity' => true]);
    $this->article = Article::factory()->create(['is_online_publishable' => true, 'unit_of_measure_id' => $unit->id]);
    PriceListItem::factory()->create(['article_id' => $this->article->id, 'price_list_id' => $this->list->id, 'price' => '1000.00']);
    $this->balance = StockBalance::factory()->create(['article_id' => $this->article->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => '0.300']);
});

test('accepts the exact decimal remainder throughout cart and order confirmation', function () {
    $paid = WebOrder::factory()->paid()->create();
    WebOrderItem::factory()->create(['web_order_id' => $paid->id, 'article_id' => $this->article->id, 'quantity' => '0.200']);

    expect(app(GetOnlineAvailability::class)->forArticle($this->article)->availableQuantity)->toBe('0.100');
    $item = app(AddArticleToCart::class)->execute($this->customer, $this->article, '0.100');
    app(UpdateCartItemQuantity::class)->execute($this->customer, $item, '0.100');
    $cart = app(GetCustomerCart::class)->execute($this->customer);
    expect($cart->has_unavailable_items)->toBeFalse()->and($cart->items[0]->is_available)->toBeTrue();

    $order = app(PlaceWebOrder::class)->execute($this->customer, DeliveryMethod::Pickup, $this->branch);
    expect($order->items->sole()->quantity)->toBe('0.100');
});

test('accumulates decimal additions exactly and rejects an excess of one thousandth', function () {
    app(AddArticleToCart::class)->execute($this->customer, $this->article, '0.100');
    $item = app(AddArticleToCart::class)->execute($this->customer, $this->article, '0.200');
    expect($item->quantity)->toBe('0.300');
    expect(fn () => app(AddArticleToCart::class)->execute($this->customer, $this->article, '0.001'))
        ->toThrow(ValidationException::class);
    expect(fn () => app(UpdateCartItemQuantity::class)->execute($this->customer, $item, '0.301'))
        ->toThrow(ValidationException::class);
    expect($item->fresh()->quantity)->toBe('0.300');
});

test('allows adjusting an insufficient line directly to a fractional maximum and unlocks checkout', function () {
    $this->balance->update(['quantity' => '2.000']);
    $item = app(AddArticleToCart::class)->execute($this->customer, $this->article, '2.000');
    $this->balance->update(['quantity' => '0.100']);
    $cart = app(GetCustomerCart::class)->execute($this->customer);
    expect($cart->has_unavailable_items)->toBeTrue()
        ->and($cart->items[0]->can_adjust_quantity)->toBeTrue()
        ->and($cart->items[0]->available_quantity)->toBe('0.100');
    app(UpdateCartItemQuantity::class)->execute($this->customer, $item, '0.100');
    expect(app(GetCustomerCart::class)->execute($this->customer)->has_unavailable_items)->toBeFalse();
});

test('does not offer quantity adjustment for articles with no stock or no price', function () {
    CartItem::factory()->create(['customer_id' => $this->customer->id, 'article_id' => $this->article->id]);
    $this->balance->update(['quantity' => '0.000']);
    expect(app(GetCustomerCart::class)->execute($this->customer)->items[0]->can_adjust_quantity)->toBeFalse();
    $this->balance->update(['quantity' => '10.000']);
    $this->article->update(['is_online_publishable' => false]);
    expect(app(GetCustomerCart::class)->execute($this->customer)->items[0]->can_adjust_quantity)->toBeFalse();
});

test('rejects quantities that cannot be represented at stock precision', function (string $quantity) {
    expect(fn () => app(AddArticleToCart::class)->execute($this->customer, $this->article, $quantity))
        ->toThrow(ValidationException::class);
})->with(['0.0001', '0.3001', '1000000000']);
