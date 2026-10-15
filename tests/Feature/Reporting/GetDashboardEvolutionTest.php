<?php

use App\Actions\Reporting\GetDashboardEvolution;
use App\Actions\Reporting\GetDashboardExpenses;
use App\Actions\Reporting\GetDashboardRevenues;
use App\Data\Reporting\DashboardEvolutionData;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Sales\Invoice;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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
});

/**
 * A confirmed counter sale with its invoice, whose net amount is the revenue EPIC-20 uses.
 */
function confirmedSaleForEvolution(PointOfSale $pointOfSale, string $net, string $confirmedAt, bool $isOnline = false): Sale
{
    $factory = $isOnline ? Sale::factory()->online() : Sale::factory();
    $total = number_format((float) $net * 1.21, 2, '.', '');

    $sale = $factory->confirmed()->create([
        'point_of_sale_id' => $pointOfSale->id,
        'confirmed_at' => $confirmedAt,
        'total_amount' => $total,
    ]);

    Invoice::factory()->create([
        'sale_id' => $sale->id,
        'net_amount' => $net,
        'vat_amount' => number_format((float) $total - (float) $net, 2, '.', ''),
        'total_amount' => $total,
    ]);

    return $sale;
}

/**
 * Runs the three dashboard Actions the same way the controller does.
 *
 * @param  array{date_from: string, date_to: string, branch_id?: ?int, channel?: ?string}  $filters
 */
function dashboardEvolutionFor(array $filters): DashboardEvolutionData
{
    $filters += ['branch_id' => null, 'channel' => null];

    return app(GetDashboardEvolution::class)->handle(
        $filters,
        app(GetDashboardRevenues::class)->handle($filters),
        app(GetDashboardExpenses::class)->handle($filters),
    );
}

test('short ranges are charted day by day with every day present', function () {
    confirmedSaleForEvolution($this->centralCounter, '1000.00', '2026-10-02 12:00:00');
    confirmedSaleForEvolution($this->centralCounter, '500.00', '2026-10-02 18:30:00');
    SupplierVoucher::factory()->invoice()->create(['issue_date' => '2026-10-04', 'due_date' => null, 'total_amount' => '300.00']);

    $evolution = dashboardEvolutionFor(['date_from' => '2026-10-01', 'date_to' => '2026-10-05']);

    expect(collect($evolution->series)->map->toArray()->all())->toBe([
        ['period' => '2026-10-01', 'net_revenue' => '0.00', 'accrued_expenses' => '0.00', 'net_result' => '0.00'],
        ['period' => '2026-10-02', 'net_revenue' => '1500.00', 'accrued_expenses' => '0.00', 'net_result' => '1500.00'],
        ['period' => '2026-10-03', 'net_revenue' => '0.00', 'accrued_expenses' => '0.00', 'net_result' => '0.00'],
        ['period' => '2026-10-04', 'net_revenue' => '0.00', 'accrued_expenses' => '300.00', 'net_result' => '-300.00'],
        ['period' => '2026-10-05', 'net_revenue' => '0.00', 'accrued_expenses' => '0.00', 'net_result' => '0.00'],
    ])
        ->and($evolution->net_result)->toBe('1200.00')
        ->and($evolution->net_margin_pct)->toBe(80.0);
});

test('long ranges are charted month by month with empty months filled with zero', function () {
    confirmedSaleForEvolution($this->centralCounter, '2000.00', '2026-07-15 12:00:00');
    confirmedSaleForEvolution($this->centralCounter, '1000.00', '2026-09-20 12:00:00');
    SupplierVoucher::factory()->invoice()->create(['issue_date' => '2026-09-01', 'due_date' => null, 'total_amount' => '400.00']);
    SupplierVoucher::factory()->creditNote()->create(['issue_date' => '2026-09-10', 'total_amount' => '100.00']);

    $evolution = dashboardEvolutionFor(['date_from' => '2026-07-01', 'date_to' => '2026-10-14']);

    expect(collect($evolution->series)->map->toArray()->all())->toBe([
        ['period' => '2026-07', 'net_revenue' => '2000.00', 'accrued_expenses' => '0.00', 'net_result' => '2000.00'],
        ['period' => '2026-08', 'net_revenue' => '0.00', 'accrued_expenses' => '0.00', 'net_result' => '0.00'],
        ['period' => '2026-09', 'net_revenue' => '1000.00', 'accrued_expenses' => '300.00', 'net_result' => '700.00'],
        ['period' => '2026-10', 'net_revenue' => '0.00', 'accrued_expenses' => '0.00', 'net_result' => '0.00'],
    ]);
});

test('net result matches the difference between the revenues and expenses blocks across two months', function () {
    $this->travelTo('2026-10-31 10:00:00');

    confirmedSaleForEvolution($this->centralCounter, '10000.00', '2026-09-10 12:00:00');
    confirmedSaleForEvolution($this->northCounter, '5000.55', '2026-10-05 12:00:00');
    SupplierVoucher::factory()->invoice()->create(['issue_date' => '2026-09-12', 'due_date' => null, 'total_amount' => '4000.10']);
    SupplierVoucher::factory()->debitNote()->create(['issue_date' => '2026-10-03', 'due_date' => null, 'total_amount' => '250.00']);
    SupplierVoucher::factory()->invoice()->create([
        'issue_date' => '2026-10-04', 'due_date' => null, 'total_amount' => '999.00', 'status' => SupplierVoucherStatus::Cancelled,
    ]);

    $this->actingAs(User::factory()->gerente()->create())
        ->get(route('dashboard', ['date_from' => '2026-09-01', 'date_to' => '2026-10-31']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('revenues.net_revenue', '15000.55')
            ->where('expenses.accrued_total', '4250.10')
            ->where('evolution.net_result', '10750.45')
            ->where('evolution.net_margin_pct', 71.7)
            ->has('evolution.series', 2)
            ->where('evolution.series.0', [
                'period' => '2026-09', 'net_revenue' => '10000.00', 'accrued_expenses' => '4000.10', 'net_result' => '5999.90',
            ])
            ->where('evolution.series.1', [
                'period' => '2026-10', 'net_revenue' => '5000.55', 'accrued_expenses' => '250.00', 'net_result' => '4750.55',
            ]));
});

test('variation compares against the previous period of the same length and reads a smaller deficit as an improvement', function () {
    SupplierVoucher::factory()->invoice()->create(['issue_date' => '2026-09-25', 'due_date' => null, 'total_amount' => '1000.00']);
    confirmedSaleForEvolution($this->centralCounter, '500.00', '2026-10-02 12:00:00');
    SupplierVoucher::factory()->invoice()->create(['issue_date' => '2026-10-03', 'due_date' => null, 'total_amount' => '1000.00']);

    $evolution = dashboardEvolutionFor(['date_from' => '2026-10-01', 'date_to' => '2026-10-10']);

    expect($evolution->net_result)->toBe('-500.00')
        ->and($evolution->net_margin_pct)->toBe(-100.0)
        ->and($evolution->net_result_variation_pct)->toBe(50.0);
});

test('margin and variation are null when there is nothing to divide by', function () {
    SupplierVoucher::factory()->invoice()->create(['issue_date' => '2026-10-03', 'due_date' => null, 'total_amount' => '1000.00']);

    $evolution = dashboardEvolutionFor(['date_from' => '2026-10-01', 'date_to' => '2026-10-10']);

    expect($evolution->net_result)->toBe('-1000.00')
        ->and($evolution->net_margin_pct)->toBeNull()
        ->and($evolution->net_result_variation_pct)->toBeNull();
});

test('branch and channel filters narrow the revenue side only', function () {
    $onlinePointOfSale = PointOfSale::factory()->create([
        'warehouse_id' => Warehouse::factory()->online()->create(['branch_id' => $this->northBranch->id])->id,
    ]);
    confirmedSaleForEvolution($this->centralCounter, '1000.00', '2026-10-02 12:00:00');
    confirmedSaleForEvolution($this->northCounter, '2000.00', '2026-10-02 12:00:00');
    confirmedSaleForEvolution($onlinePointOfSale, '4000.00', '2026-10-03 12:00:00', isOnline: true);
    SupplierVoucher::factory()->invoice()->create(['issue_date' => '2026-10-02', 'due_date' => null, 'total_amount' => '100.00']);

    $byBranch = dashboardEvolutionFor(['date_from' => '2026-10-01', 'date_to' => '2026-10-03', 'branch_id' => $this->northBranch->id]);
    $byChannel = dashboardEvolutionFor(['date_from' => '2026-10-01', 'date_to' => '2026-10-03', 'channel' => 'online']);

    expect($byBranch->net_result)->toBe('5900.00')
        ->and($byBranch->series[1]->net_revenue)->toBe('2000.00')
        ->and($byBranch->series[1]->accrued_expenses)->toBe('100.00')
        ->and($byChannel->net_result)->toBe('3900.00')
        ->and($byChannel->series[1]->net_revenue)->toBe('0.00')
        ->and($byChannel->series[2]->net_revenue)->toBe('4000.00');
});
