<?php

use App\Enums\Purchasing\PurchaseOrderStatus;
use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Catalog\Article;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\User;
use Database\Seeders\Inventory\StockMovementTypeSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(StockMovementTypeSeeder::class);
});

test('remito requires an active warehouse when not imputed to purchase orders', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $article = Article::factory()->create();

    $response = $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Remito->value,
        'letter' => SupplierVoucherLetter::R->value,
        'point_of_sale' => '0001',
        'number' => '00000001',
        'issue_date' => today()->toDateString(),
        'total_amount' => '0,00',
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '5',
                'unit_of_measure' => 'un',
                'unit_price' => '0,00',
                'line_total' => '0,00',
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['warehouse_id']);
});

test('remito rejects letters other than R and X', function (string $letter) {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $article = Article::factory()->create();

    $response = $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Remito->value,
        'letter' => $letter,
        'point_of_sale' => '0001',
        'number' => '00000002',
        'warehouse_id' => $warehouse->id,
        'issue_date' => today()->toDateString(),
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '5',
                'unit_of_measure' => 'un',
                'unit_price' => '0,00',
                'line_total' => '0,00',
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['letter']);
})->with(['A', 'B', 'C', 'M']);

test('remito prohibits due_date', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $article = Article::factory()->create();

    $response = $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Remito->value,
        'letter' => SupplierVoucherLetter::R->value,
        'point_of_sale' => '0001',
        'number' => '00000003',
        'warehouse_id' => $warehouse->id,
        'issue_date' => today()->toDateString(),
        'due_date' => today()->addDays(15)->toDateString(),
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '5',
                'unit_of_measure' => 'un',
                'unit_price' => '0,00',
                'line_total' => '0,00',
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['due_date']);
});

test('remito requires at least one catalog article line to enter stock', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $response = $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Remito->value,
        'letter' => SupplierVoucherLetter::R->value,
        'point_of_sale' => '0001',
        'number' => '00000004',
        'warehouse_id' => $warehouse->id,
        'issue_date' => today()->toDateString(),
        'items' => [
            [
                'article_id' => null,
                'description' => 'Gasto de flete o concepto libre',
                'quantity' => '1',
                'unit_of_measure' => '—',
                'unit_price' => '0,00',
                'line_total' => '0,00',
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['items']);
});

test('free remito registers with confirmed status, zero amounts and links to warehouse without creating debt', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create(['name' => 'Depósito Central']);
    $article = Article::factory()->create();

    $response = $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Remito->value,
        'letter' => SupplierVoucherLetter::R->value,
        'point_of_sale' => '0001',
        'number' => '00000005',
        'warehouse_id' => $warehouse->id,
        'issue_date' => today()->toDateString(),
        'total_amount' => '',
        'notes' => 'Remito de entrega directa',
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '12,5',
                'unit_of_measure' => 'kg',
                'unit_price' => '',
                'line_total' => '',
            ],
            [
                'article_id' => null,
                'description' => 'Nota al pie del remito',
                'quantity' => '1',
                'unit_of_measure' => '—',
                'unit_price' => '',
                'line_total' => '',
            ],
        ],
    ]);

    $voucher = SupplierVoucher::where('supplier_id', $supplier->id)
        ->where('number', '00000005')
        ->firstOrFail();

    $response->assertRedirect(route('purchasing.vouchers.show', $voucher));

    expect($voucher->type)->toBe(SupplierVoucherType::Remito)
        ->and($voucher->status)->toBe(SupplierVoucherStatus::Confirmed)
        ->and($voucher->warehouse_id)->toBe($warehouse->id)
        ->and((float) $voucher->total_amount)->toBe(0.0)
        ->and((float) $voucher->outstandingAmount())->toBe(0.0)
        ->and($voucher->isOverdue())->toBeFalse()
        ->and($voucher->items)->toHaveCount(2);

    $this->assertDatabaseHas('stock_movements', [
        'supplier_voucher_id' => $voucher->id,
        'warehouse_id' => $warehouse->id,
    ]);
});

test('remito imputed to purchase order derives warehouse automatically', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create(['name' => 'Depósito Norte']);
    $otherWarehouse = Warehouse::factory()->create(['name' => 'Depósito Sur']);
    $article = Article::factory()->create();

    $order = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'status' => PurchaseOrderStatus::Issued,
    ]);

    $orderItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'article_id' => $article->id,
        'quantity' => '10.000',
        'unit_price' => '100.00',
        'line_total' => '1000.00',
    ]);

    $response = $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Remito->value,
        'letter' => SupplierVoucherLetter::R->value,
        'point_of_sale' => '0001',
        'number' => '00000006',
        // warehouse_id omitted: derived automatically from PO!
        'issue_date' => today()->toDateString(),
        'total_amount' => '0,00',
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '10',
                'unit_of_measure' => 'un',
                'unit_price' => '0,00',
                'line_total' => '0,00',
                'purchase_order_item_id' => $orderItem->id,
            ],
        ],
    ]);

    $voucher = SupplierVoucher::where('number', '00000006')->firstOrFail();
    $response->assertRedirect(route('purchasing.vouchers.show', $voucher));
    expect($voucher->warehouse_id)->toBe($warehouse->id); // Derived from PO!
});

test('remito accepts multiple purchase orders when they share the same warehouse', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create(['name' => 'Depósito Compartido']);
    $firstArticle = Article::factory()->create();
    $secondArticle = Article::factory()->create();

    $firstOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'status' => PurchaseOrderStatus::Issued,
    ]);
    $secondOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'status' => PurchaseOrderStatus::Issued,
    ]);

    $firstOrderItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $firstOrder->id,
        'article_id' => $firstArticle->id,
        'quantity' => '4.000',
        'unit_price' => '100.00',
        'line_total' => '400.00',
    ]);
    $secondOrderItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $secondOrder->id,
        'article_id' => $secondArticle->id,
        'quantity' => '6.000',
        'unit_price' => '100.00',
        'line_total' => '600.00',
    ]);

    $response = $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Remito->value,
        'letter' => SupplierVoucherLetter::R->value,
        'point_of_sale' => '0001',
        'number' => '00000014',
        'issue_date' => today()->toDateString(),
        'total_amount' => '0,00',
        'items' => [
            [
                'article_id' => $firstArticle->id,
                'description' => $firstArticle->description,
                'quantity' => '4',
                'unit_of_measure' => 'un',
                'unit_price' => '0,00',
                'line_total' => '0,00',
                'purchase_order_item_id' => $firstOrderItem->id,
            ],
            [
                'article_id' => $secondArticle->id,
                'description' => $secondArticle->description,
                'quantity' => '6',
                'unit_of_measure' => 'un',
                'unit_price' => '0,00',
                'line_total' => '0,00',
                'purchase_order_item_id' => $secondOrderItem->id,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $voucher = SupplierVoucher::query()->where('number', '00000014')->firstOrFail();
    expect($voucher->warehouse_id)->toBe($warehouse->id)
        ->and($voucher->stockMovement)->not->toBeNull()
        ->and($voucher->stockMovement?->items)->toHaveCount(2);
});

test('remito rejects conflicting warehouse when imputed to purchase order', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create(['name' => 'Depósito Norte']);
    $otherWarehouse = Warehouse::factory()->create(['name' => 'Depósito Sur']);
    $article = Article::factory()->create();

    $order = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'status' => PurchaseOrderStatus::Issued,
    ]);

    $orderItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'article_id' => $article->id,
        'quantity' => '10.000',
        'unit_price' => '100.00',
        'line_total' => '1000.00',
    ]);

    $response = $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Remito->value,
        'letter' => SupplierVoucherLetter::R->value,
        'point_of_sale' => '0001',
        'number' => '00000009',
        'warehouse_id' => $otherWarehouse->id, // Sent conflicting warehouse
        'issue_date' => today()->toDateString(),
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '10',
                'unit_of_measure' => 'un',
                'unit_price' => '0,00',
                'line_total' => '0,00',
                'purchase_order_item_id' => $orderItem->id,
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['warehouse_id']);
});

test('remito rejects imputing purchase orders from different warehouses', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $wh1 = Warehouse::factory()->create(['name' => 'Depósito 1']);
    $wh2 = Warehouse::factory()->create(['name' => 'Depósito 2']);
    $article1 = Article::factory()->create();
    $article2 = Article::factory()->create();

    $order1 = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $wh1->id,
        'status' => PurchaseOrderStatus::Issued,
    ]);
    $item1 = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order1->id,
        'article_id' => $article1->id,
        'quantity' => '5.000',
        'unit_price' => '100.00',
        'line_total' => '500.00',
    ]);

    $order2 = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $wh2->id,
        'status' => PurchaseOrderStatus::Issued,
    ]);
    $item2 = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order2->id,
        'article_id' => $article2->id,
        'quantity' => '5.000',
        'unit_price' => '100.00',
        'line_total' => '500.00',
    ]);

    $response = $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Remito->value,
        'letter' => SupplierVoucherLetter::X->value,
        'point_of_sale' => '0001',
        'number' => '00000007',
        'issue_date' => today()->toDateString(),
        'items' => [
            [
                'article_id' => $article1->id,
                'description' => $article1->description,
                'quantity' => '5',
                'unit_of_measure' => 'un',
                'unit_price' => '0,00',
                'line_total' => '0,00',
                'purchase_order_item_id' => $item1->id,
            ],
            [
                'article_id' => $article2->id,
                'description' => $article2->description,
                'quantity' => '5',
                'unit_of_measure' => 'un',
                'unit_price' => '0,00',
                'line_total' => '0,00',
                'purchase_order_item_id' => $item2->id,
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['warehouse_id']);
});

test('remito show page exposes warehouse name, confirmed status, and linked stock movement', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create(['name' => 'Depósito Central']);
    $article = Article::factory()->create();

    $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Remito->value,
        'letter' => SupplierVoucherLetter::R->value,
        'point_of_sale' => '0001',
        'number' => '00000008',
        'warehouse_id' => $warehouse->id,
        'issue_date' => today()->toDateString(),
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '3',
                'unit_of_measure' => 'un',
                'unit_price' => '0,00',
                'line_total' => '0,00',
            ],
        ],
    ]);

    $voucher = SupplierVoucher::where('number', '00000008')->firstOrFail();

    $this->actingAs($user)->get(route('purchasing.vouchers.show', $voucher))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/vouchers/show')
            ->where('voucher.type', SupplierVoucherType::Remito->value)
            ->where('voucher.status', SupplierVoucherStatus::Confirmed->value)
            ->where('voucher.warehouse_name', 'Depósito Central')
            ->whereNot('voucher.stock_movement_id', null));
});
