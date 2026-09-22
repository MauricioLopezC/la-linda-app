<?php

use App\Actions\Purchasing\UpdateLastPurchaseCost;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Catalog\Article;
use App\Models\Catalog\ArticleSupplier;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\SupplierVoucherItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** @return array<string, mixed> */
function hu038VoucherData(Supplier $supplier, array $items, string $number, array $overrides = []): array
{
    return array_merge([
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Invoice->value,
        'letter' => 'A',
        'point_of_sale' => '1',
        'number' => $number,
        'issue_date' => today()->toDateString(),
        'due_date' => null,
        'total_amount' => collect($items)->sum(fn (array $item): float => (float) $item['line_total']),
        'items' => $items,
    ], $overrides);
}

/** @return array<string, mixed> */
function hu038Item(?Article $article, string $price, string $description = 'Compra'): array
{
    return [
        'article_id' => $article?->id,
        'description' => $description,
        'quantity' => '1',
        'unit_of_measure' => 'un',
        'unit_price' => $price,
        'line_total' => $price,
    ];
}

test('invoice without a purchase order updates the associated supplier cost', function () {
    $supplier = Supplier::factory()->create();
    $article = Article::factory()->create();
    $association = ArticleSupplier::factory()->create([
        'supplier_id' => $supplier->id,
        'article_id' => $article->id,
        'last_cost' => '50.00',
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.vouchers.store'), hu038VoucherData($supplier, [hu038Item($article, '125.50')], '1'))
        ->assertSessionHasNoErrors();

    expect($association->fresh()->last_cost)->toBe('125.50');

    $voucher = SupplierVoucher::query()->sole();
    $this->actingAs(User::factory()->create())->post(route('purchasing.vouchers.annul', $voucher), [
        'reason' => 'Corrección',
    ])->assertSessionHasNoErrors();

    expect($association->fresh()->last_cost)->toBeNull();
});

test('invoice for an unassociated article rolls back the whole voucher', function () {
    $supplier = Supplier::factory()->create();
    $associatedArticle = Article::factory()->create();
    $unassociatedArticle = Article::factory()->create();
    $association = ArticleSupplier::factory()->create([
        'supplier_id' => $supplier->id,
        'article_id' => $associatedArticle->id,
        'last_cost' => '15.00',
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.vouchers.store'), hu038VoucherData($supplier, [
            hu038Item($associatedArticle, '20.00'),
            hu038Item($unassociatedArticle, '25.00'),
        ], '2'))
        ->assertSessionHasErrors('items.1.article_id');

    expect(SupplierVoucher::query()->count())->toBe(0)
        ->and(ArticleSupplier::query()->count())->toBe(1)
        ->and($association->fresh()->last_cost)->toBe('15.00');
});

test('credit and debit notes do not change the last invoice cost', function (SupplierVoucherType $type) {
    $supplier = Supplier::factory()->create();
    $article = Article::factory()->create();
    $association = ArticleSupplier::factory()->create([
        'supplier_id' => $supplier->id,
        'article_id' => $article->id,
        'last_cost' => '30.00',
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.vouchers.store'), hu038VoucherData(
            $supplier,
            [hu038Item($article, '40.00')],
            '3',
            ['type' => $type->value]
        ))
        ->assertSessionHasNoErrors();

    expect($association->fresh()->last_cost)->toBe('30.00');
})->with([SupplierVoucherType::CreditNote, SupplierVoucherType::DebitNote]);

test('concept lines do not set an article cost and a repeated article uses its last line', function () {
    $supplier = Supplier::factory()->create();
    $article = Article::factory()->create();
    $association = ArticleSupplier::factory()->create([
        'supplier_id' => $supplier->id,
        'article_id' => $article->id,
        'last_cost' => null,
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('purchasing.vouchers.store'), hu038VoucherData($supplier, [
            hu038Item($article, '10.00'),
            hu038Item(null, '5.00', 'Flete'),
            hu038Item($article, '12.00'),
        ], '4'))
        ->assertSessionHasNoErrors();

    expect($association->fresh()->last_cost)->toBe('12.00');
});

test('registration order determines cost and annulment restores the last active invoice', function () {
    $supplier = Supplier::factory()->create();
    $article = Article::factory()->create();
    $association = ArticleSupplier::factory()->create([
        'supplier_id' => $supplier->id,
        'article_id' => $article->id,
        'last_cost' => null,
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('purchasing.vouchers.store'), hu038VoucherData(
        $supplier, [hu038Item($article, '20.00')], '5', ['issue_date' => today()->subDays(2)->toDateString()]
    ))->assertSessionHasNoErrors();
    $first = SupplierVoucher::query()->where('number', '00000005')->firstOrFail();

    $this->actingAs($user)->post(route('purchasing.vouchers.store'), hu038VoucherData(
        $supplier, [hu038Item($article, '30.00')], '6', ['issue_date' => today()->subDays(5)->toDateString()]
    ))->assertSessionHasNoErrors();
    $second = SupplierVoucher::query()->where('number', '00000006')->firstOrFail();
    expect($association->fresh()->last_cost)->toBe('30.00');

    $this->actingAs($user)->post(route('purchasing.vouchers.annul', $first), ['reason' => 'Corrección'])
        ->assertSessionHasNoErrors();
    expect($association->fresh()->last_cost)->toBe('30.00');

    $this->actingAs($user)->post(route('purchasing.vouchers.annul', $second), ['reason' => 'Corrección'])
        ->assertSessionHasNoErrors();
    expect($association->fresh()->last_cost)->toBeNull();
});

test('annulling the newest invoice restores the cost of the previous active invoice', function () {
    $supplier = Supplier::factory()->create();
    $article = Article::factory()->create();
    $association = ArticleSupplier::factory()->create([
        'supplier_id' => $supplier->id,
        'article_id' => $article->id,
        'last_cost' => null,
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('purchasing.vouchers.store'), hu038VoucherData(
        $supplier, [hu038Item($article, '20.00')], '7'
    ))->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('purchasing.vouchers.store'), hu038VoucherData(
        $supplier, [hu038Item($article, '30.00')], '8'
    ))->assertSessionHasNoErrors();

    $lastVoucher = SupplierVoucher::query()->where('number', '00000008')->firstOrFail();
    $this->actingAs($user)->post(route('purchasing.vouchers.annul', $lastVoucher), [
        'reason' => 'Corrección',
    ])->assertSessionHasNoErrors();

    expect($association->fresh()->last_cost)->toBe('20.00');
});

test('cost updates preload all article supplier associations in one query', function () {
    $supplier = Supplier::factory()->create();
    $articles = Article::factory()->count(3)->create();
    $associations = $articles->map(fn (Article $article) => ArticleSupplier::factory()->create([
        'supplier_id' => $supplier->id,
        'article_id' => $article->id,
        'last_cost' => null,
    ]));
    $voucher = SupplierVoucher::factory()->invoice()->create(['supplier_id' => $supplier->id]);

    $articles->each(fn (Article $article, int $index) => SupplierVoucherItem::factory()->create([
        'supplier_voucher_id' => $voucher->id,
        'position' => $index + 1,
        'article_id' => $article->id,
        'unit_price' => number_format(100 + $index, 2, '.', ''),
    ]));

    DB::enableQueryLog();
    app(UpdateLastPurchaseCost::class)->handle($voucher);
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $associationSelects = $queries->filter(
        fn (array $query): bool => str_contains(strtolower($query['query']), 'from "article_supplier"')
    );

    expect($associationSelects)->toHaveCount(1)
        ->and($associations->map(fn (ArticleSupplier $association): string => $association->fresh()->last_cost)->all())
        ->toBe(['100.00', '101.00', '102.00']);
});

test('cost recalculation loads associations and previous invoice items in batches', function () {
    $supplier = Supplier::factory()->create();
    $articles = Article::factory()->count(3)->create();
    $associations = $articles->map(fn (Article $article) => ArticleSupplier::factory()->create([
        'supplier_id' => $supplier->id,
        'article_id' => $article->id,
        'last_cost' => '200.00',
    ]));
    $previousVoucher = SupplierVoucher::factory()->invoice()->create(['supplier_id' => $supplier->id]);
    $annulledVoucher = SupplierVoucher::factory()->invoice()->create([
        'supplier_id' => $supplier->id,
        'status' => SupplierVoucherStatus::Cancelled,
    ]);

    foreach ($articles as $index => $article) {
        SupplierVoucherItem::factory()->create([
            'supplier_voucher_id' => $previousVoucher->id,
            'position' => $index + 1,
            'article_id' => $article->id,
            'unit_price' => number_format(100 + $index, 2, '.', ''),
        ]);
        SupplierVoucherItem::factory()->create([
            'supplier_voucher_id' => $annulledVoucher->id,
            'position' => $index + 1,
            'article_id' => $article->id,
            'unit_price' => '200.00',
        ]);
    }

    DB::enableQueryLog();
    app(UpdateLastPurchaseCost::class)->recalculateForAnnulledVoucher($annulledVoucher);
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $associationSelects = $queries->filter(
        fn (array $query): bool => str_contains(strtolower($query['query']), 'from "article_supplier"')
    );
    $itemSelects = $queries->filter(
        fn (array $query): bool => str_contains(strtolower($query['query']), 'from "supplier_voucher_items"')
    );

    expect($associationSelects)->toHaveCount(1)
        ->and($itemSelects)->toHaveCount(2)
        ->and($associations->map(fn (ArticleSupplier $association): string => $association->fresh()->last_cost)->all())
        ->toBe(['100.00', '101.00', '102.00']);
});
