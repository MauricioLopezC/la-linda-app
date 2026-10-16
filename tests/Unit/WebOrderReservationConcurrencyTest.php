<?php

use App\Models\Catalog\Article;
use App\Models\Ecommerce\WebOrder;
use App\Models\Ecommerce\WebOrderItem;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('serializes simultaneous reservations for the last units on PostgreSQL', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Requires PostgreSQL row locks and independent connections.');
    }

    $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();

    $warehouse = Warehouse::factory()->online()->create();
    $article = Article::factory()->create();
    StockBalance::factory()->create(['warehouse_id' => $warehouse->id, 'article_id' => $article->id, 'quantity' => '3.000']);
    $orders = collect([WebOrder::factory()->create(), WebOrder::factory()->create()]);
    foreach ($orders as $order) {
        WebOrderItem::factory()->create(['web_order_id' => $order->id, 'article_id' => $article->id, 'quantity' => '2.000']);
    }

    $workerCode = <<<'CODE'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::statement("set application_name to 'hu048-reservation-concurrency'");
try {
    app(App\Actions\Ecommerce\ReserveWebOrderStock::class)->execute(App\Models\Ecommerce\WebOrder::findOrFail($argv[1]));
    echo 'reserved';
} catch (Illuminate\Validation\ValidationException $exception) {
    echo 'blocked';
}
CODE;
    $connection = config('database.connections.pgsql');
    $environment = [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
        'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
        'DB_DATABASE' => $connection['database'], 'DB_USERNAME' => $connection['username'],
        'DB_PASSWORD' => $connection['password'],
    ];
    $workers = [];

    DB::beginTransaction();
    try {
        Article::query()->whereKey($article->id)->lockForUpdate()->firstOrFail();
        foreach ($orders as $order) {
            $worker = new Process([PHP_BINARY, '-r', $workerCode, (string) $order->id], base_path(), $environment);
            $worker->setTimeout(20)->start();
            $workers[] = $worker;
        }

        $deadline = microtime(true) + 10;
        do {
            DB::select('select pg_stat_clear_snapshot()');
            $waiting = (int) DB::selectOne("select count(*) as total from pg_stat_activity where datname = current_database() and application_name = 'hu048-reservation-concurrency' and wait_event_type = 'Lock'")->total;
            if ($waiting === 2) {
                break;
            }
            usleep(20_000);
        } while (microtime(true) < $deadline);
    } finally {
        DB::rollBack();
    }

    try {
        expect($waiting)->toBe(2, implode(' ', array_map(fn (Process $worker): string => $worker->getErrorOutput(), $workers)));
        $outcomes = [];
        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput());
            $outcomes[] = $worker->getOutput();
        }
        sort($outcomes);
        expect($outcomes)->toBe(['blocked', 'reserved'])
            ->and(WebOrder::query()->whereNotNull('stock_reserved_until')->count())->toBe(1);
    } finally {
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
    }
});
