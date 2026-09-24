<?php

use App\Actions\Inventory\CreateStockMovementFromVoucher;
use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Catalog\Article;
use App\Models\Catalog\ArticleSupplier;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementType;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\User;
use Database\Seeders\Inventory\StockMovementTypeSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(StockMovementTypeSeeder::class);
});

test('registering a remito automatically creates a purchase_entry stock movement and updates stock_balances', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create(['name' => 'Depósito Central']);
    $article = Article::factory()->create();

    // Initial balance: 10.000
    StockBalance::create([
        'warehouse_id' => $warehouse->id,
        'article_id' => $article->id,
        'quantity' => '10.000',
    ]);

    $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Remito->value,
        'letter' => SupplierVoucherLetter::R->value,
        'point_of_sale' => '0001',
        'number' => '00000010',
        'warehouse_id' => $warehouse->id,
        'issue_date' => today()->toDateString(),
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '15.5',
                'unit_of_measure' => 'kg',
                'unit_price' => '0,00',
                'line_total' => '0,00',
            ],
            [
                'article_id' => null,
                'description' => 'Gasto de envío sin stock',
                'quantity' => '1',
                'unit_of_measure' => '—',
                'unit_price' => '0,00',
                'line_total' => '0,00',
            ],
        ],
    ])->assertSessionHasNoErrors();

    $voucher = SupplierVoucher::where('number', '00000010')->firstOrFail();

    $movement = StockMovement::where('supplier_voucher_id', $voucher->id)->first();
    expect($movement)->not->toBeNull()
        ->and($movement->warehouse_id)->toBe($warehouse->id)
        ->and($movement->user_id)->toBe($user->id)
        ->and($movement->type->code)->toBe(StockMovementType::CODE_PURCHASE_ENTRY)
        ->and($movement->items)->toHaveCount(1); // Only catalog article, no concept!

    $item = $movement->items->first();
    expect($item->article_id)->toBe($article->id)
        ->and((float) $item->quantity)->toBe(15.5)
        ->and($item->system_quantity)->toBeNull();

    // Stock balance updated: 10 + 15.5 = 25.5
    $balance = StockBalance::where('warehouse_id', $warehouse->id)
        ->where('article_id', $article->id)
        ->first();
    expect((float) $balance->quantity)->toBe(25.5);
});

test('stock movement cannot be duplicated for the same supplier voucher', function () {
    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();
    $article = Article::factory()->create();
    $voucher = SupplierVoucher::factory()->create([
        'warehouse_id' => $warehouse->id,
        'type' => SupplierVoucherType::Remito,
        'letter' => SupplierVoucherLetter::R,
        'status' => SupplierVoucherStatus::Confirmed,
    ]);

    $voucher->items()->create([
        'position' => 1,
        'article_id' => $article->id,
        'description' => $article->description,
        'quantity' => '7.000',
        'unit_of_measure' => 'un',
        'unit_price' => '0.00',
        'line_total' => '0.00',
    ]);

    [$firstMovement, $secondMovement] = DB::transaction(fn (): array => [
        app(CreateStockMovementFromVoucher::class)->handle($voucher, $user->id),
        app(CreateStockMovementFromVoucher::class)->handle($voucher, $user->id),
    ]);

    expect($secondMovement->is($firstMovement))->toBeTrue()
        ->and(StockMovement::query()->where('supplier_voucher_id', $voucher->id)->count())->toBe(1)
        ->and((float) StockBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('article_id', $article->id)
            ->value('quantity'))->toBe(7.0);
});

test('supplier vouchers other than remitos do not create stock movements', function (SupplierVoucherType $type) {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $article = Article::factory()->create();
    ArticleSupplier::factory()->create([
        'article_id' => $article->id,
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => $type->value,
        'letter' => SupplierVoucherLetter::A->value,
        'point_of_sale' => '0001',
        'number' => '00000020',
        'issue_date' => today()->toDateString(),
        'due_date' => null,
        'total_amount' => '10,00',
        'items' => [[
            'article_id' => $article->id,
            'description' => $article->description,
            'quantity' => '1',
            'unit_of_measure' => 'un',
            'unit_price' => '10,00',
            'line_total' => '10,00',
        ]],
    ])->assertSessionHasNoErrors();

    expect(StockMovement::query()->count())->toBe(0);
})->with([
    'factura' => SupplierVoucherType::Invoice,
    'nota de crédito' => SupplierVoucherType::CreditNote,
    'nota de débito' => SupplierVoucherType::DebitNote,
]);

test('annulling a remito creates a purchase_entry_reversal movement and restores stock', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $article = Article::factory()->create();

    $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Remito->value,
        'letter' => SupplierVoucherLetter::R->value,
        'point_of_sale' => '0001',
        'number' => '00000011',
        'warehouse_id' => $warehouse->id,
        'issue_date' => today()->toDateString(),
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '20',
                'unit_of_measure' => 'un',
                'unit_price' => '0,00',
                'line_total' => '0,00',
            ],
        ],
    ])->assertSessionHasNoErrors();

    $voucher = SupplierVoucher::where('number', '00000011')->firstOrFail();
    $originalMovement = StockMovement::where('supplier_voucher_id', $voucher->id)->firstOrFail();

    // Balance after entry is 20
    expect((float) StockBalance::where('warehouse_id', $warehouse->id)->where('article_id', $article->id)->value('quantity'))->toBe(20.0);

    // Annul the voucher
    $this->actingAs($user)->post(route('purchasing.vouchers.annul', $voucher), [
        'reason' => 'Mercadería devuelta por rotura en transporte',
    ])->assertSessionHasNoErrors();

    $voucher->refresh();
    expect($voucher->status)->toBe(SupplierVoucherStatus::Cancelled);

    // Original movement was NOT deleted
    expect(StockMovement::find($originalMovement->id))->not->toBeNull();

    // Reversal movement exists and links to original movement
    $reversalMovement = StockMovement::where('reversal_of_movement_id', $originalMovement->id)->first();
    expect($reversalMovement)->not->toBeNull()
        ->and($reversalMovement->type->code)->toBe(StockMovementType::CODE_PURCHASE_ENTRY_REVERSAL)
        ->and($reversalMovement->supplier_voucher_id)->toBeNull()
        ->and($reversalMovement->items)->toHaveCount(1);

    $reversalItem = $reversalMovement->items->first();
    expect((float) $reversalItem->quantity)->toBe(-20.0);

    // Balance restored to 0
    expect((float) StockBalance::where('warehouse_id', $warehouse->id)->where('article_id', $article->id)->value('quantity'))->toBe(0.0);
});

test('annulling a remito fails atomically if available stock would become negative', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $article = Article::factory()->create();

    $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Remito->value,
        'letter' => SupplierVoucherLetter::R->value,
        'point_of_sale' => '0001',
        'number' => '00000012',
        'warehouse_id' => $warehouse->id,
        'issue_date' => today()->toDateString(),
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '20',
                'unit_of_measure' => 'un',
                'unit_price' => '0,00',
                'line_total' => '0,00',
            ],
        ],
    ])->assertSessionHasNoErrors();

    $voucher = SupplierVoucher::where('number', '00000012')->firstOrFail();
    $originalMovement = StockMovement::where('supplier_voucher_id', $voucher->id)->firstOrFail();

    // Simulate stock consumption: balance reduced to 5
    StockBalance::where('warehouse_id', $warehouse->id)
        ->where('article_id', $article->id)
        ->update(['quantity' => '5.000']);

    // Attempting to reverse 20 when only 5 are in stock must fail
    $response = $this->actingAs($user)->post(route('purchasing.vouchers.annul', $voucher), [
        'reason' => 'Error de ingreso',
    ]);

    $response->assertSessionHasErrors(['stock']);

    $voucher->refresh();
    expect($voucher->status)->toBe(SupplierVoucherStatus::Confirmed);

    // No reversal movement created
    expect(StockMovement::where('reversal_of_movement_id', $originalMovement->id)->exists())->toBeFalse();

    // Balance untouched
    expect((float) StockBalance::where('warehouse_id', $warehouse->id)->where('article_id', $article->id)->value('quantity'))->toBe(5.0);
});

test('stock movements history and detail pages display voucher and reversal navigability links', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create(['name' => 'Depósito Central']);
    $article = Article::factory()->create();

    $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Remito->value,
        'letter' => SupplierVoucherLetter::R->value,
        'point_of_sale' => '0001',
        'number' => '00000013',
        'warehouse_id' => $warehouse->id,
        'issue_date' => today()->toDateString(),
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '10',
                'unit_of_measure' => 'un',
                'unit_price' => '0,00',
                'line_total' => '0,00',
            ],
        ],
    ])->assertSessionHasNoErrors();

    $voucher = SupplierVoucher::where('number', '00000013')->firstOrFail();
    $movement = StockMovement::where('supplier_voucher_id', $voucher->id)->firstOrFail();

    // History Index
    $this->actingAs($user)->get(route('inventory.movements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/movements/index')
            ->where('movements.data.0.supplier_voucher_id', $voucher->id)
            ->where('movements.data.0.supplier_voucher_formatted_number', 'R 0001-00000013'));

    // Detail Show
    $this->actingAs($user)->get(route('inventory.adjustments.show', $movement))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/adjustments/show')
            ->where('movement.supplier_voucher_id', $voucher->id)
            ->where('movement.supplier_voucher_formatted_number', 'R 0001-00000013')
            ->has('movement.items.0', fn (Assert $item) => $item
                ->has('quantity')
                ->missing('system_quantity')
                ->missing('final_quantity')
                ->etc()));

    // Annul voucher and verify reversal navigation
    $this->actingAs($user)->post(route('purchasing.vouchers.annul', $voucher), [
        'reason' => 'Anulación de prueba',
    ])->assertSessionHasNoErrors();

    $reversalMovement = StockMovement::where('reversal_of_movement_id', $movement->id)->firstOrFail();

    $this->actingAs($user)->get(route('inventory.adjustments.show', $reversalMovement))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/adjustments/show')
            ->where('movement.supplier_voucher_id', $voucher->id)
            ->where('movement.reversal_of_movement_id', $movement->id));
});
