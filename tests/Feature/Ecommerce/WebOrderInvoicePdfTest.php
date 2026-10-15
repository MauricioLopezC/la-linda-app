<?php

use App\Actions\Ecommerce\ConvertPaidWebOrderToSale;
use App\Enums\Customers\CustomerTaxCondition;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Security\UserRole;
use App\Models\Catalog\Article;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Inventory\Warehouse;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\VatRate;
use App\Models\Sales\PointOfSale;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->vat21 = VatRate::query()->where('percentage', 21.0)->first()
        ?? VatRate::factory()->create(['percentage' => 21.0, 'description' => 'IVA 21%']);

    $this->onlineWarehouse = Warehouse::factory()->online()->create([
        'name' => 'Depósito E-commerce Test',
        'is_active' => true,
    ]);

    $this->onlinePos = PointOfSale::factory()->create([
        'warehouse_id' => $this->onlineWarehouse->id,
        'number' => 2,
        'is_active' => true,
    ]);

    $this->onlinePriceList = PriceList::factory()->forChannel(PriceListChannel::Online)->create();

    $this->article = Article::factory()->create([
        'vat_rate_id' => $this->vat21->id,
        'description' => 'Leche Entera La Linda 1L',
    ]);

    $this->customer = Customer::factory()->create([
        'tax_condition' => CustomerTaxCondition::ConsumidorFinal,
    ]);

    $this->clientUser = User::factory()->create([
        'role' => UserRole::Cliente,
        'customer_id' => $this->customer->id,
    ]);

    $this->otherCustomer = Customer::factory()->create();
    $this->otherClientUser = User::factory()->create([
        'role' => UserRole::Cliente,
        'customer_id' => $this->otherCustomer->id,
    ]);

    $this->paidOrder = WebOrder::factory()->paid()->shipping('1210.00')->create([
        'customer_id' => $this->customer->id,
        'items_amount' => '2420.00',
        'shipping_cost' => '1210.00',
        'total_amount' => '3630.00',
        'paid_amount' => '3630.00',
        'mp_payment_id' => 'mp-test-pdf-12345',
    ]);

    WebOrderItem::factory()->create([
        'web_order_id' => $this->paidOrder->id,
        'article_id' => $this->article->id,
        'quantity' => '2.000',
        'unit_price' => '1210.00',
        'price_list_id' => $this->onlinePriceList->id,
        'line_total' => '2420.00',
    ]);

    // Convert to sale and issue invoice
    $this->sale = app(ConvertPaidWebOrderToSale::class)->execute($this->paidOrder);
});

test('customer can see and stream the same invoice throughout order fulfillment', function (WebOrderStatus $status) {
    $operator = User::factory()->create(['role' => UserRole::Deposito]);
    $this->paidOrder->update([
        'status' => $status,
        'preparation_started_at' => $status !== WebOrderStatus::Paid ? now() : null,
        'prepared_by' => $status !== WebOrderStatus::Paid ? $operator->id : null,
        'delivered_at' => $status === WebOrderStatus::Delivered ? now() : null,
        'delivered_by' => $status === WebOrderStatus::Delivered ? $operator->id : null,
    ]);

    $this->actingAs($this->clientUser)
        ->get(route('tienda.orders.show', $this->paidOrder))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ecommerce/orders/show')
            ->where('order.status', $status->value)
            ->where('order.status_label', $this->paidOrder->statusLabel())
            ->where('order.paid_amount', '3630.00')
            ->where('order.invoice.id', $this->sale->invoice->id)
            ->where('order.invoice.voucher_label', $this->sale->invoice->voucherLabel()));

    $response = $this->actingAs($this->clientUser)
        ->get(route('tienda.orders.invoice.pdf', $this->paidOrder));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');

    // The stream disposition is inline
    $disposition = (string) $response->headers->get('content-disposition');
    expect($disposition)->toContain('inline');
})->with([
    'paid' => WebOrderStatus::Paid,
    'in preparation' => WebOrderStatus::InPreparation,
    'dispatched' => WebOrderStatus::Delivered,
]);

test('authenticated customer can download invoice pdf with query parameter download=1', function () {
    $response = $this->actingAs($this->clientUser)
        ->get(route('tienda.orders.invoice.pdf', ['web_order' => $this->paidOrder, 'download' => 1]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');

    $disposition = (string) $response->headers->get('content-disposition');
    expect($disposition)->toContain('attachment')
        ->and($disposition)->toContain('Factura_B_');
});

test('customer cannot access invoice pdf of an order belonging to another customer', function () {
    $response = $this->actingAs($this->otherClientUser)
        ->get(route('tienda.orders.invoice.pdf', $this->paidOrder));

    $response->assertForbidden();
});

test('unauthenticated user is redirected to login when trying to access invoice pdf', function () {
    $response = $this->get(route('tienda.orders.invoice.pdf', $this->paidOrder));

    $response->assertRedirect(route('login'));
});

test('returns 404 if order does not have an issued invoice', function () {
    $unpaidOrder = WebOrder::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => WebOrderStatus::Pending,
    ]);

    $response = $this->actingAs($this->clientUser)
        ->get(route('tienda.orders.invoice.pdf', $unpaidOrder));

    $response->assertNotFound();
});

test('internal user can view invoice pdf of the online sale through sales.invoice.pdf', function () {
    $adminUser = User::factory()->administrador()->create();

    $response = $this->actingAs($adminUser)
        ->get(route('sales.sales.invoice.pdf', $this->sale));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});
