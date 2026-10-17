<?php

use App\Actions\Reporting\GetDashboardRevenues;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Sales\CashMovementType;
use App\Enums\Sales\CashSessionStatus;
use App\Enums\Sales\SaleChannel;
use App\Enums\Sales\SaleStatus;
use App\Models\Ecommerce\WebOrder;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementItem;
use App\Models\Inventory\StockMovementType;
use App\Models\Purchasing\PaymentOrder;
use App\Models\Sales\CashMovement;
use App\Models\Sales\CashSession;
use App\Models\Sales\Invoice;
use App\Models\Sales\Sale;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Date;

/**
 * Seeding a few days of history takes seconds, so each test groups several checks over one run.
 */
beforeEach(function () {
    config(['demo.sales_history_days' => 10]);
    Date::setTestNow(Date::parse('2026-10-16 15:00:00'));

    $this->seed(DatabaseSeeder::class);
});

it('simulates invoiced and paid counter and online sales with every cash session closed', function () {
    $counterSales = Sale::query()->where('channel', SaleChannel::Mostrador)->where('status', SaleStatus::Confirmed)->count();
    $onlineSales = Sale::query()->where('channel', SaleChannel::Online)->where('status', SaleStatus::Confirmed)->count();

    expect($counterSales)->toBeGreaterThan(40)
        ->and($onlineSales)->toBeGreaterThan(5)
        ->and(Sale::query()->min('confirmed_at'))->toStartWith('2026-10-06')
        ->and(PaymentOrder::query()->exists())->toBeFalse();

    $revenues = app(GetDashboardRevenues::class)->handle([
        'date_from' => '2026-10-06',
        'date_to' => '2026-10-16',
        'branch_id' => null,
        'channel' => null,
    ]);

    expect((float) $revenues->net_revenue)->toBeGreaterThan(0);

    // Every confirmed sale is invoiced and paid for its exact total, with correlative numbers.
    Sale::query()->where('status', SaleStatus::Confirmed)->with('invoice')->each(function (Sale $sale): void {
        expect($sale->invoice)->not->toBeNull()
            ->and($sale->invoice->total_amount)->toBe($sale->total_amount);

        if ($sale->channel === SaleChannel::Mostrador) {
            $paidCents = CashMovement::query()
                ->where('sale_id', $sale->id)
                ->where('type', CashMovementType::Sale)
                ->get()
                ->sum(fn (CashMovement $movement): int => (int) round((float) $movement->amount * 100));

            expect($paidCents)->toBe((int) round((float) $sale->total_amount * 100));
        }
    });

    Invoice::query()->get()->groupBy(fn (Invoice $invoice): string => $invoice->point_of_sale_id.'-'.$invoice->type->value)
        ->each(fn ($invoices) => expect($invoices->pluck('number')->sort()->values()->all())->toBe(range(1, $invoices->count())));

    expect(CashSession::query()->where('status', CashSessionStatus::Open)->exists())->toBeFalse()
        ->and(Sale::query()->where('status', SaleStatus::Open)->exists())->toBeFalse();
});

it('keeps the stock consistent with the kardex, never negative, and delivers the online orders', function () {
    expect(StockBalance::query()->where('quantity', '<', 0)->exists())->toBeFalse();

    StockBalance::query()->each(function (StockBalance $balance): void {
        $kardex = StockMovementItem::query()
            ->where('article_id', $balance->article_id)
            ->whereHas('stockMovement', fn ($query) => $query->where('warehouse_id', $balance->warehouse_id))
            ->sum('quantity');

        expect(round((float) $kardex, 3))->toBe(round((float) $balance->quantity, 3));
    });

    $initialLoad = StockMovementType::query()->where('code', StockMovementType::CODE_INITIAL_LOAD)->value('id');

    StockMovement::query()->get()->groupBy('warehouse_id')->each(function ($movements) use ($initialLoad): void {
        expect($movements->sortBy(['created_at', 'id'])->first()->stock_movement_type_id)->toBe($initialLoad);
    });

    expect(WebOrder::query()->where('status', WebOrderStatus::Delivered)->exists())->toBeTrue()
        ->and(WebOrder::query()->where('status', WebOrderStatus::Pending)->exists())->toBeFalse();
});

it('does not simulate the history twice', function () {
    $sales = Sale::query()->count();

    $this->seed(DatabaseSeeder::class);

    expect(Sale::query()->count())->toBe($sales);
});
