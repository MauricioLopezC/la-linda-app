<?php

use App\Actions\Ecommerce\MarkWebOrderAsPaid;
use App\Actions\Sales\ConfirmSalePayment;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Catalog\Article;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\VatRate;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard reports actual counter confirmation and online payment without duplicating a replayed payment', function (string $shipping, string $onlineTotal, string $total, string $net, string $vat, string $ticket) {
    $this->travelTo('2026-10-14 12:00:00');

    $central = Branch::factory()->create(['name' => 'Central']);
    $north = Branch::factory()->create(['name' => 'Norte']);
    $counter = PointOfSale::factory()->create([
        'warehouse_id' => Warehouse::factory()->create(['branch_id' => $central->id])->id,
    ]);
    PointOfSale::factory()->create([
        'warehouse_id' => Warehouse::factory()->online()->create(['branch_id' => $north->id])->id,
    ]);
    $rate = VatRate::factory()->create(['percentage' => 21]);
    $article = Article::factory()->create(['vat_rate_id' => $rate->id]);
    $priceList = PriceList::factory()->forChannel(PriceListChannel::Online)->create();
    $cash = PaymentMethod::factory()->cash()->create(['name' => 'Efectivo']);
    $sale = Sale::factory()->create(['point_of_sale_id' => $counter->id]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'article_id' => $article->id,
        'quantity' => '1.000',
        'unit_price' => '1210.00',
        'line_total' => '1210.00',
    ]);
    $sale->recalculateTotal();

    $orderFactory = WebOrder::factory()->state(['items_amount' => '2420.00', 'total_amount' => '2420.00']);
    $order = ($shipping === '0.00' ? $orderFactory : $orderFactory->shipping($shipping))->create();
    WebOrderItem::factory()->create([
        'web_order_id' => $order->id,
        'article_id' => $article->id,
        'price_list_id' => $priceList->id,
        'quantity' => '2.000',
        'unit_price' => '1210.00',
        'line_total' => '2420.00',
    ]);

    $confirmed = app(ConfirmSalePayment::class)->handle($sale, [
        ['payment_method_id' => $cash->id, 'amount' => '1210.00', 'tendered_amount' => '1500.00'],
    ], $sale->user);

    expect($confirmed->status)->toBe(SaleStatus::Confirmed)
        ->and($confirmed->invoice->total_amount)->toBe('1210.00')
        ->and($confirmed->cashMovements->sole()->amount)->toBe('1210.00');

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('revenues.total_invoiced', '1210.00')
            ->where('revenues.confirmed_sales_count', 1)
            ->where('revenues.by_channel.1.amount', '0.00')
        );

    $payments = app(MarkWebOrderAsPaid::class);
    $paid = $payments->execute($order, 'dashboard-integration-payment', $onlineTotal, now());
    $payments->execute($paid, 'dashboard-integration-payment', $onlineTotal, now());

    expect($paid->sale->invoice->total_amount)->toBe($onlineTotal)
        ->and($paid->sale->cashMovements)->toHaveCount(0)
        ->and(Invoice::query()->count())->toBe(2)
        ->and(Sale::query()->where('web_order_id', $order->id)->count())->toBe(1);

    $this->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('revenues.total_invoiced', $total)
            ->where('revenues.net_revenue', $net)
            ->where('revenues.vat_total', $vat)
            ->where('revenues.confirmed_sales_count', 2)
            ->where('revenues.average_ticket', $ticket)
            ->has('revenues.by_channel', 2)
            ->where('revenues.by_channel.0.label', 'Mostrador')
            ->where('revenues.by_channel.0.amount', '1210.00')
            ->where('revenues.by_channel.1.label', 'Online')
            ->where('revenues.by_channel.1.amount', $onlineTotal)
            ->has('revenues.by_branch', 2)
            ->where('revenues.by_branch.0.label', 'Norte')
            ->where('revenues.by_branch.0.amount', $onlineTotal)
            ->where('revenues.by_branch.1.label', 'Central')
            ->where('revenues.by_branch.1.amount', '1210.00')
            ->has('revenues.by_payment_method', 2)
            ->where('revenues.by_payment_method.0.label', 'Mercado Pago (online)')
            ->where('revenues.by_payment_method.0.amount', $onlineTotal)
            ->where('revenues.by_payment_method.1.label', 'Efectivo')
            ->where('revenues.by_payment_method.1.amount', '1210.00')
        );

    $this->get(route('dashboard', ['branch_id' => $north->id, 'channel' => 'online']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('revenues.total_invoiced', $onlineTotal)
            ->where('revenues.confirmed_sales_count', 1)
            ->has('revenues.by_channel', 1)
            ->where('revenues.by_channel.0.label', 'Online')
            ->has('revenues.by_branch', 1)
            ->where('revenues.by_branch.0.label', 'Norte')
            ->has('revenues.by_payment_method', 1)
            ->where('revenues.by_payment_method.0.amount', $onlineTotal)
        );
})->with([
    'pickup' => ['0.00', '2420.00', '3630.00', '3000.00', '630.00', '1815.00'],
    'shipping' => ['121.00', '2541.00', '3751.00', '3100.00', '651.00', '1875.50'],
]);
