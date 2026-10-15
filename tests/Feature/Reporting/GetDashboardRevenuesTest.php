<?php

use App\Actions\Reporting\GetDashboardRevenues;
use App\Data\Reporting\DashboardBreakdownItemData;
use App\Data\Reporting\DashboardRevenuesData;
use App\Models\Ecommerce\WebOrder;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\Sales\CashMovement;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;

beforeEach(function () {
    $this->travelTo('2026-10-14 10:00:00');

    $this->centralBranch = Branch::factory()->create(['name' => 'Central']);
    $this->northBranch = Branch::factory()->create(['name' => 'Norte']);

    $this->centralCounter = PointOfSale::factory()->create([
        'warehouse_id' => Warehouse::factory()->create(['branch_id' => $this->centralBranch->id])->id,
    ]);
    $this->northCounter = PointOfSale::factory()->create([
        'warehouse_id' => Warehouse::factory()->create(['branch_id' => $this->northBranch->id])->id,
    ]);
    $this->onlinePointOfSale = PointOfSale::factory()->create([
        'warehouse_id' => Warehouse::factory()->online()->create(['branch_id' => $this->centralBranch->id])->id,
    ]);

    $this->cash = PaymentMethod::factory()->cash()->create(['name' => 'Efectivo']);
    $this->card = PaymentMethod::factory()->card()->create(['name' => 'Tarjeta de Débito']);
});

/**
 * A confirmed counter sale with its invoice and one `venta` cash movement per payment.
 *
 * @param  array<int, array{0: PaymentMethod, 1: string}>  $payments
 */
function confirmedCounterSaleForRevenues(PointOfSale $pointOfSale, string $net, string $vat, string $total, array $payments, string $confirmedAt = '2026-10-10 12:00:00'): Sale
{
    $sale = Sale::factory()->confirmed()->create([
        'point_of_sale_id' => $pointOfSale->id,
        'confirmed_at' => $confirmedAt,
        'total_amount' => $total,
    ]);

    Invoice::factory()->create([
        'sale_id' => $sale->id,
        'net_amount' => $net,
        'vat_amount' => $vat,
        'total_amount' => $total,
    ]);

    foreach ($payments as [$paymentMethod, $amount]) {
        CashMovement::factory()->forSale($sale)->create([
            'payment_method_id' => $paymentMethod->id,
            'amount' => $amount,
            'user_id' => $sale->user_id,
        ]);
    }

    return $sale;
}

/**
 * A confirmed online sale (EPIC-15) with its invoice, collected through Mercado Pago.
 */
function confirmedOnlineSaleForRevenues(PointOfSale $pointOfSale, string $net, string $vat, string $total, string $confirmedAt = '2026-10-11 12:00:00'): Sale
{
    $sale = Sale::factory()->online()->confirmed()->create([
        'point_of_sale_id' => $pointOfSale->id,
        'confirmed_at' => $confirmedAt,
        'total_amount' => $total,
    ]);

    Invoice::factory()->create([
        'sale_id' => $sale->id,
        'net_amount' => $net,
        'vat_amount' => $vat,
        'total_amount' => $total,
    ]);

    return $sale;
}

/**
 * @param  array{branch_id?: ?int, channel?: ?string, date_from?: string, date_to?: string}  $overrides
 */
function dashboardRevenues(array $overrides = []): DashboardRevenuesData
{
    return app(GetDashboardRevenues::class)->handle([
        'date_from' => $overrides['date_from'] ?? '2026-10-01',
        'date_to' => $overrides['date_to'] ?? '2026-10-14',
        'branch_id' => $overrides['branch_id'] ?? null,
        'channel' => $overrides['channel'] ?? null,
    ]);
}

/**
 * @param  array<int, DashboardBreakdownItemData>  $items
 * @return array<string, array{amount: string, count: int}>
 */
function breakdownByLabel(array $items): array
{
    return collect($items)
        ->mapWithKeys(fn (DashboardBreakdownItemData $item): array => [$item->label => ['amount' => $item->amount, 'count' => $item->count]])
        ->all();
}

test('total invoiced is the exact sum of the invoices of confirmed counter and online sales', function () {
    confirmedCounterSaleForRevenues($this->centralCounter, '1000.00', '210.00', '1210.00', [[$this->cash, '1210.00']]);
    confirmedOnlineSaleForRevenues($this->onlinePointOfSale, '826.45', '173.55', '1000.00');

    $revenues = dashboardRevenues();

    expect($revenues->total_invoiced)->toBe('2210.00')
        ->and($revenues->net_revenue)->toBe('1826.45')
        ->and($revenues->vat_total)->toBe('383.55')
        ->and($revenues->confirmed_sales_count)->toBe(2)
        ->and($revenues->average_ticket)->toBe('1105.00')
        ->and(breakdownByLabel($revenues->by_channel))->toBe([
            'Mostrador' => ['amount' => '1210.00', 'count' => 1],
            'Online' => ['amount' => '1000.00', 'count' => 1],
        ]);
});

test('open and discarded sales, pending web orders and sales outside the period are left out', function () {
    confirmedCounterSaleForRevenues($this->centralCounter, '100.00', '21.00', '121.00', [[$this->cash, '121.00']]);

    Sale::factory()->create(['point_of_sale_id' => $this->centralCounter->id, 'total_amount' => '500.00']);
    Sale::factory()->discarded()->create(['point_of_sale_id' => $this->centralCounter->id, 'total_amount' => '700.00']);
    WebOrder::factory()->create();
    confirmedCounterSaleForRevenues($this->centralCounter, '1000.00', '210.00', '1210.00', [[$this->cash, '1210.00']], '2026-09-30 23:59:59');
    confirmedCounterSaleForRevenues($this->centralCounter, '1000.00', '210.00', '1210.00', [[$this->cash, '1210.00']], '2026-10-15 00:00:00');

    $revenues = dashboardRevenues();

    expect($revenues->total_invoiced)->toBe('121.00')
        ->and($revenues->confirmed_sales_count)->toBe(1)
        ->and(breakdownByLabel($revenues->by_payment_method))->toBe([
            'Efectivo' => ['amount' => '121.00', 'count' => 1],
        ]);
});

test('a sale confirmed on the last day of the period counts until the end of that day', function () {
    confirmedCounterSaleForRevenues($this->centralCounter, '100.00', '21.00', '121.00', [[$this->cash, '121.00']], '2026-10-14 23:59:59');

    expect(dashboardRevenues()->total_invoiced)->toBe('121.00');
});

test('revenues are broken down by branch through the point of sale warehouse', function () {
    confirmedCounterSaleForRevenues($this->centralCounter, '100.00', '21.00', '121.00', [[$this->cash, '121.00']]);
    confirmedCounterSaleForRevenues($this->northCounter, '1000.00', '210.00', '1210.00', [[$this->card, '1210.00']]);
    confirmedOnlineSaleForRevenues($this->onlinePointOfSale, '826.45', '173.55', '1000.00');

    expect(breakdownByLabel(dashboardRevenues()->by_branch))->toBe([
        'Norte' => ['amount' => '1210.00', 'count' => 1],
        'Central' => ['amount' => '1121.00', 'count' => 2],
    ]);
});

test('revenues can be filtered by branch', function () {
    confirmedCounterSaleForRevenues($this->centralCounter, '100.00', '21.00', '121.00', [[$this->cash, '121.00']]);
    confirmedCounterSaleForRevenues($this->northCounter, '1000.00', '210.00', '1210.00', [[$this->card, '1210.00']]);

    $revenues = dashboardRevenues(['branch_id' => $this->northBranch->id]);

    expect($revenues->total_invoiced)->toBe('1210.00')
        ->and($revenues->confirmed_sales_count)->toBe(1)
        ->and(breakdownByLabel($revenues->by_branch))->toBe([
            'Norte' => ['amount' => '1210.00', 'count' => 1],
        ])
        ->and(breakdownByLabel($revenues->by_payment_method))->toBe([
            'Tarjeta de Débito' => ['amount' => '1210.00', 'count' => 1],
        ]);
});

test('revenues can be filtered by channel', function (string $channel, string $total, array $byChannel, array $byPaymentMethod) {
    confirmedCounterSaleForRevenues($this->centralCounter, '1000.00', '210.00', '1210.00', [[$this->cash, '1210.00']]);
    confirmedOnlineSaleForRevenues($this->onlinePointOfSale, '826.45', '173.55', '1000.00');

    $revenues = dashboardRevenues(['channel' => $channel]);

    expect($revenues->total_invoiced)->toBe($total)
        ->and(breakdownByLabel($revenues->by_channel))->toBe($byChannel)
        ->and(breakdownByLabel($revenues->by_payment_method))->toBe($byPaymentMethod);
})->with([
    'mostrador' => ['mostrador', '1210.00', ['Mostrador' => ['amount' => '1210.00', 'count' => 1]], ['Efectivo' => ['amount' => '1210.00', 'count' => 1]]],
    'online' => ['online', '1000.00', ['Online' => ['amount' => '1000.00', 'count' => 1]], ['Mercado Pago (online)' => ['amount' => '1000.00', 'count' => 1]]],
]);

test('collected money is broken down by the payment method of each payment, with online sales as Mercado Pago', function () {
    confirmedCounterSaleForRevenues($this->centralCounter, '1000.00', '210.00', '1210.00', [[$this->cash, '210.00'], [$this->card, '1000.00']]);
    confirmedCounterSaleForRevenues($this->northCounter, '100.00', '21.00', '121.00', [[$this->cash, '121.00']]);
    confirmedOnlineSaleForRevenues($this->onlinePointOfSale, '1239.67', '260.33', '1500.00');

    expect(breakdownByLabel(dashboardRevenues()->by_payment_method))->toBe([
        'Mercado Pago (online)' => ['amount' => '1500.00', 'count' => 1],
        'Tarjeta de Débito' => ['amount' => '1000.00', 'count' => 1],
        'Efectivo' => ['amount' => '331.00', 'count' => 2],
    ]);
});

test('total invoiced variation compares against the previous period of the same length', function () {
    confirmedCounterSaleForRevenues($this->centralCounter, '1000.00', '210.00', '1210.00', [[$this->cash, '1210.00']], '2026-10-05 12:00:00');
    confirmedCounterSaleForRevenues($this->centralCounter, '800.00', '168.00', '968.00', [[$this->cash, '968.00']], '2026-09-25 12:00:00');
    confirmedCounterSaleForRevenues($this->centralCounter, '5000.00', '1050.00', '6050.00', [[$this->cash, '6050.00']], '2026-09-10 12:00:00');

    $revenues = dashboardRevenues(['date_from' => '2026-10-01', 'date_to' => '2026-10-10']);

    expect($revenues->total_invoiced_variation_pct)->toBe(25.0);
});

test('an empty period returns zeros and no variation', function () {
    $revenues = dashboardRevenues();

    expect($revenues->total_invoiced)->toBe('0.00')
        ->and($revenues->net_revenue)->toBe('0.00')
        ->and($revenues->vat_total)->toBe('0.00')
        ->and($revenues->confirmed_sales_count)->toBe(0)
        ->and($revenues->average_ticket)->toBe('0.00')
        ->and($revenues->total_invoiced_variation_pct)->toBeNull()
        ->and(breakdownByLabel($revenues->by_channel))->toBe([
            'Mostrador' => ['amount' => '0.00', 'count' => 0],
            'Online' => ['amount' => '0.00', 'count' => 0],
        ])
        ->and($revenues->by_branch)->toBe([])
        ->and($revenues->by_payment_method)->toBe([]);
});
