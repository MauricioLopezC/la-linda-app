<?php

use App\Data\Reporting\DashboardData;
use App\Data\Reporting\DashboardEvolutionData;
use App\Data\Reporting\DashboardEvolutionPointData;
use App\Data\Reporting\DashboardFiltersData;
use App\Data\Reporting\DashboardOperationalData;
use App\Data\Reporting\DashboardStockAlertData;
use App\Data\Reporting\DashboardSupplierRankingData;
use App\Data\Reporting\DashboardTopArticleData;

test('dashboard serializes evolution and operational blocks without losing decimal precision', function () {
    $dashboard = new DashboardData(
        filters: new DashboardFiltersData('2026-10-01', '2026-10-14', null, null),
        branches: [],
        evolution: new DashboardEvolutionData(
            net_result: '-0.01',
            net_margin_pct: -0.5,
            net_result_variation_pct: null,
            series: [new DashboardEvolutionPointData('2026-10-01', '2.00', '2.01', '-0.01')],
        ),
        operational: new DashboardOperationalData(
            top_articles: [new DashboardTopArticleData(1, 'Yerba a granel', '1.125', '9999999999.99')],
            stock_alerts: [new DashboardStockAlertData(1, 'Yerba a granel', 2, 'Central', '-0.125', true)],
            supplier_ranking: [new DashboardSupplierRankingData(3, 'Proveedor', '9999999999.99', '0.01')],
            pending_web_orders_count: 2,
        ),
    );

    $serialized = $dashboard->toArray();

    expect($serialized['evolution'])->toBe([
        'net_result' => '-0.01',
        'net_margin_pct' => -0.5,
        'net_result_variation_pct' => null,
        'series' => [[
            'period' => '2026-10-01',
            'net_revenue' => '2.00',
            'accrued_expenses' => '2.01',
            'net_result' => '-0.01',
        ]],
    ])->and($serialized['operational'])->toBe([
        'top_articles' => [[
            'article_id' => 1,
            'description' => 'Yerba a granel',
            'quantity' => '1.125',
            'total_invoiced' => '9999999999.99',
        ]],
        'stock_alerts' => [[
            'article_id' => 1,
            'article_description' => 'Yerba a granel',
            'warehouse_id' => 2,
            'warehouse_name' => 'Central',
            'quantity' => '-0.125',
            'has_stock_conflict' => true,
        ]],
        'supplier_ranking' => [[
            'supplier_id' => 3,
            'name' => 'Proveedor',
            'purchases_total' => '9999999999.99',
            'outstanding_balance' => '0.01',
        ]],
        'pending_web_orders_count' => 2,
    ]);
});

test('dashboard distinguishes empty computed blocks from unavailable blocks', function () {
    $dashboard = new DashboardData(
        filters: new DashboardFiltersData('2026-10-01', '2026-10-14', null, null),
        branches: [],
        evolution: new DashboardEvolutionData('0.00', null, null, []),
        operational: new DashboardOperationalData([], [], [], 0),
    );

    $serialized = $dashboard->toArray();

    expect($serialized['revenues'])->toBeNull()
        ->and($serialized['expenses'])->toBeNull()
        ->and($serialized['evolution'])->toBe([
            'net_result' => '0.00',
            'net_margin_pct' => null,
            'net_result_variation_pct' => null,
            'series' => [],
        ])->and($serialized['operational'])->toBe([
            'top_articles' => [],
            'stock_alerts' => [],
            'supplier_ranking' => [],
            'pending_web_orders_count' => 0,
        ]);
});
