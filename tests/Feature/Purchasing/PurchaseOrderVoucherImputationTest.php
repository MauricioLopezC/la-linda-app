<?php

use App\Enums\Purchasing\PurchaseOrderStatus;
use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Catalog\Article;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Models\Purchasing\PurchaseOrderVoucherImputation;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('user can impute a single purchase order item when registering a supplier voucher', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $article = Article::factory()->create();

    $order = PurchaseOrder::factory()->issued()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_number' => 'OC-00000001',
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'article_id' => $article->id,
        'quantity' => '10.000',
        'unit_price' => '100.00',
        'line_total' => '1000.00',
    ]);

    $voucherPayload = [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Invoice->value,
        'letter' => SupplierVoucherLetter::A->value,
        'point_of_sale' => '1',
        'number' => '101',
        'issue_date' => today()->toDateString(),
        'due_date' => today()->addDays(30)->toDateString(),
        'total_amount' => '1.000,00',
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '10,000',
                'unit_of_measure' => 'un',
                'unit_price' => '100,00',
                'line_total' => '1.000,00',
                'purchase_order_item_id' => $poItem->id,
            ],
        ],
    ];

    $response = $this->actingAs($user)->post(route('purchasing.vouchers.store'), $voucherPayload);
    $response->assertSessionHasNoErrors()->assertRedirect();

    expect(PurchaseOrderVoucherImputation::count())->toBe(1);

    $imputation = PurchaseOrderVoucherImputation::first();
    expect($imputation->purchase_order_item_id)->toBe($poItem->id)
        ->and((float) $imputation->quantity_received)->toEqualWithDelta(10.0, 0.001)
        ->and((float) $imputation->quantity_excess)->toEqualWithDelta(0.0, 0.001);

    $order->refresh();
    expect($order->status)->toBe(PurchaseOrderStatus::Fulfilled)
        ->and($poItem->quantityReceived())->toBe('10.000')
        ->and($poItem->quantityPending())->toBe('0.000')
        ->and($poItem->isFullyReceived())->toBeTrue();
});

test('order with pending balance remains in emitida status until fully received', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $article = Article::factory()->create();

    $order = PurchaseOrder::factory()->issued()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'article_id' => $article->id,
        'quantity' => '10.000',
        'unit_price' => '100.00',
        'line_total' => '1000.00',
    ]);

    // Partial delivery: 4 of 10
    $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Invoice->value,
        'letter' => SupplierVoucherLetter::A->value,
        'point_of_sale' => '1',
        'number' => '102',
        'issue_date' => today()->toDateString(),
        'due_date' => today()->addDays(30)->toDateString(),
        'total_amount' => '400,00',
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '4,000',
                'unit_of_measure' => 'un',
                'unit_price' => '100,00',
                'line_total' => '400,00',
                'purchase_order_item_id' => $poItem->id,
            ],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe(PurchaseOrderStatus::Issued)
        ->and($poItem->quantityReceived())->toBe('4.000')
        ->and($poItem->quantityPending())->toBe('6.000')
        ->and($poItem->isFullyReceived())->toBeFalse();
});

test('surplus / excess delivery is accepted and recorded in quantity_excess without capping inventory', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $article = Article::factory()->create();

    $order = PurchaseOrder::factory()->issued()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
    ]);

    // Order requested 100.000 kg
    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'article_id' => $article->id,
        'quantity' => '100.000',
        'unit_price' => '50.00',
        'line_total' => '5000.00',
    ]);

    // Supplier delivered 104.250 kg (excess of 4.250 kg)
    $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Invoice->value,
        'letter' => SupplierVoucherLetter::A->value,
        'point_of_sale' => '1',
        'number' => '103',
        'issue_date' => today()->toDateString(),
        'due_date' => today()->addDays(30)->toDateString(),
        'total_amount' => '5.212,50',
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '104,250',
                'unit_of_measure' => 'kg',
                'unit_price' => '50,00',
                'line_total' => '5.212,50',
                'purchase_order_item_id' => $poItem->id,
            ],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $imputation = PurchaseOrderVoucherImputation::first();
    expect((float) $imputation->quantity_received)->toEqualWithDelta(100.0, 0.001)
        ->and((float) $imputation->quantity_excess)->toEqualWithDelta(4.250, 0.001);

    $order->refresh();
    expect($order->status)->toBe(PurchaseOrderStatus::Fulfilled)
        ->and($poItem->quantityReceived())->toBe('100.000')
        ->and($poItem->quantityExcess())->toBe('4.250')
        ->and($poItem->quantityPending())->toBe('0.000')
        ->and($poItem->isFullyReceived())->toBeTrue();

    // The voucher item should reflect the full 104.250 billed
    $voucher = SupplierVoucher::first();
    expect((float) $voucher->items->first()->quantity)->toEqualWithDelta(104.250, 0.001);
});

test('multiple vouchers can partially impute the same purchase order line until fulfilled', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $article = Article::factory()->create();

    $order = PurchaseOrder::factory()->issued()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'article_id' => $article->id,
        'quantity' => '20.000',
        'unit_price' => '10.00',
        'line_total' => '200.00',
    ]);

    // Voucher 1: delivers 8
    $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Invoice->value,
        'letter' => SupplierVoucherLetter::A->value,
        'point_of_sale' => '1',
        'number' => '104',
        'issue_date' => today()->toDateString(),
        'due_date' => today()->addDays(30)->toDateString(),
        'total_amount' => '80,00',
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '8,000',
                'unit_of_measure' => 'un',
                'unit_price' => '10,00',
                'line_total' => '80,00',
                'purchase_order_item_id' => $poItem->id,
            ],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Issued);

    // Voucher 2: delivers 14 (completes the 12 pending + 2 excess)
    $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Invoice->value,
        'letter' => SupplierVoucherLetter::A->value,
        'point_of_sale' => '1',
        'number' => '105',
        'issue_date' => today()->toDateString(),
        'due_date' => today()->addDays(30)->toDateString(),
        'total_amount' => '140,00',
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '14,000',
                'unit_of_measure' => 'un',
                'unit_price' => '10,00',
                'line_total' => '140,00',
                'purchase_order_item_id' => $poItem->id,
            ],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe(PurchaseOrderStatus::Fulfilled)
        ->and($poItem->quantityReceived())->toBe('20.000')
        ->and($poItem->quantityExcess())->toBe('2.000')
        ->and($poItem->quantityPending())->toBe('0.000');
});

test('a single voucher can impute items from multiple purchase orders of the same supplier', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $articleA = Article::factory()->create();
    $articleB = Article::factory()->create();

    $order1 = PurchaseOrder::factory()->issued()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_number' => 'OC-00000010',
    ]);
    $item1 = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order1->id,
        'article_id' => $articleA->id,
        'quantity' => '5.000',
        'unit_price' => '10.00',
        'line_total' => '50.00',
    ]);

    $order2 = PurchaseOrder::factory()->issued()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_number' => 'OC-00000011',
    ]);
    $item2 = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order2->id,
        'article_id' => $articleB->id,
        'quantity' => '8.000',
        'unit_price' => '20.00',
        'line_total' => '160.00',
    ]);

    // Single invoice receives items from both orders
    $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Invoice->value,
        'letter' => SupplierVoucherLetter::A->value,
        'point_of_sale' => '1',
        'number' => '106',
        'issue_date' => today()->toDateString(),
        'due_date' => today()->addDays(30)->toDateString(),
        'total_amount' => '210,00',
        'items' => [
            [
                'article_id' => $articleA->id,
                'description' => $articleA->description,
                'quantity' => '5,000',
                'unit_of_measure' => 'un',
                'unit_price' => '10,00',
                'line_total' => '50,00',
                'purchase_order_item_id' => $item1->id,
            ],
            [
                'article_id' => $articleB->id,
                'description' => $articleB->description,
                'quantity' => '8,000',
                'unit_of_measure' => 'un',
                'unit_price' => '20,00',
                'line_total' => '160,00',
                'purchase_order_item_id' => $item2->id,
            ],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($order1->fresh()->status)->toBe(PurchaseOrderStatus::Fulfilled)
        ->and($order2->fresh()->status)->toBe(PurchaseOrderStatus::Fulfilled);
});

test('imputing an item from a different supplier fails validation', function () {
    $user = User::factory()->create();
    $supplierA = Supplier::factory()->create();
    $supplierB = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $article = Article::factory()->create();

    $orderB = PurchaseOrder::factory()->issued()->create([
        'supplier_id' => $supplierB->id,
        'warehouse_id' => $warehouse->id,
    ]);
    $itemB = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $orderB->id,
        'article_id' => $article->id,
        'quantity' => '5.000',
    ]);

    // Voucher is for Supplier A, but attempts to impute PO of Supplier B
    $response = $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplierA->id,
        'type' => SupplierVoucherType::Invoice->value,
        'letter' => SupplierVoucherLetter::A->value,
        'point_of_sale' => '1',
        'number' => '107',
        'issue_date' => today()->toDateString(),
        'due_date' => today()->addDays(30)->toDateString(),
        'total_amount' => '50,00',
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '5,000',
                'unit_of_measure' => 'un',
                'unit_price' => '10,00',
                'line_total' => '50,00',
                'purchase_order_item_id' => $itemB->id,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('items.0.purchase_order_item_id');
});

test('imputing an item from a draft purchase order fails validation', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $article = Article::factory()->create();

    $draftOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);
    $item = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $draftOrder->id,
        'article_id' => $article->id,
        'quantity' => '5.000',
    ]);

    $response = $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Invoice->value,
        'letter' => SupplierVoucherLetter::A->value,
        'point_of_sale' => '1',
        'number' => '108',
        'issue_date' => today()->toDateString(),
        'due_date' => today()->addDays(30)->toDateString(),
        'total_amount' => '50,00',
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '5,000',
                'unit_of_measure' => 'un',
                'unit_price' => '10,00',
                'line_total' => '50,00',
                'purchase_order_item_id' => $item->id,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('items.0.purchase_order_item_id');
});

test('annulling a supplier voucher restores purchase order pending balance and reverts status to emitida', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $article = Article::factory()->create();

    $order = PurchaseOrder::factory()->issued()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
    ]);
    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'article_id' => $article->id,
        'quantity' => '10.000',
        'unit_price' => '100.00',
        'line_total' => '1000.00',
    ]);

    // Create voucher that fulfills the PO
    $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Invoice->value,
        'letter' => SupplierVoucherLetter::A->value,
        'point_of_sale' => '1',
        'number' => '109',
        'issue_date' => today()->toDateString(),
        'due_date' => today()->addDays(30)->toDateString(),
        'total_amount' => '1.000,00',
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '10,000',
                'unit_of_measure' => 'un',
                'unit_price' => '100,00',
                'line_total' => '1.000,00',
                'purchase_order_item_id' => $poItem->id,
            ],
        ],
    ]);

    $order->refresh();
    expect($order->status)->toBe(PurchaseOrderStatus::Fulfilled);

    $voucher = SupplierVoucher::where('number', '00000109')->firstOrFail();

    // Annul the voucher
    $this->actingAs($user)->post(route('purchasing.vouchers.annul', $voucher), [
        'reason' => 'Error en comprobante',
    ])->assertRedirect();

    $voucher->refresh();
    expect($voucher->status)->toBe(SupplierVoucherStatus::Cancelled);

    // Order should revert back to Issued, with 10 pending again!
    $order->refresh();
    expect($order->status)->toBe(PurchaseOrderStatus::Issued)
        ->and($poItem->quantityReceived())->toBe('0.000')
        ->and($poItem->quantityPending())->toBe('10.000')
        ->and($poItem->isFullyReceived())->toBeFalse();
});

test('associable purchase orders endpoint returns only issued orders with pending items for the given supplier', function () {
    $user = User::factory()->create();
    $supplierA = Supplier::factory()->create();
    $supplierB = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $article = Article::factory()->create();

    // Order 1: Issued with pending items for Supplier A (should be returned)
    $order1 = PurchaseOrder::factory()->issued()->create([
        'supplier_id' => $supplierA->id,
        'warehouse_id' => $warehouse->id,
        'order_number' => 'OC-00000021',
    ]);
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order1->id,
        'article_id' => $article->id,
        'quantity' => '10.000',
    ]);

    // Order 2: Draft for Supplier A (should NOT be returned)
    $order2 = PurchaseOrder::factory()->create([
        'supplier_id' => $supplierA->id,
        'warehouse_id' => $warehouse->id,
        'order_number' => 'OC-00000022',
        'status' => PurchaseOrderStatus::Draft,
    ]);
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order2->id,
        'article_id' => $article->id,
        'quantity' => '5.000',
    ]);

    // Order 3: Cancelled for Supplier A (should NOT be returned)
    $order3 = PurchaseOrder::factory()->cancelled()->create([
        'supplier_id' => $supplierA->id,
        'warehouse_id' => $warehouse->id,
        'order_number' => 'OC-00000023',
    ]);
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order3->id,
        'article_id' => $article->id,
        'quantity' => '5.000',
    ]);

    // Order 4: Issued for Supplier B (should NOT be returned)
    $order4 = PurchaseOrder::factory()->issued()->create([
        'supplier_id' => $supplierB->id,
        'warehouse_id' => $warehouse->id,
        'order_number' => 'OC-00000024',
    ]);
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order4->id,
        'article_id' => $article->id,
        'quantity' => '5.000',
    ]);

    $response = $this->actingAs($user)->getJson(
        route('purchasing.vouchers.associable-purchase-orders', ['supplier_id' => $supplierA->id])
    );

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['order_number' => 'OC-00000021']);
});

test('purchase order show page provides imputed vouchers and received breakdown props', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $article = Article::factory()->create();

    $order = PurchaseOrder::factory()->issued()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_number' => 'OC-00000030',
    ]);
    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'article_id' => $article->id,
        'quantity' => '10.000',
        'unit_price' => '100.00',
        'line_total' => '1000.00',
    ]);

    // Impute 6 units
    $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Invoice->value,
        'letter' => SupplierVoucherLetter::A->value,
        'point_of_sale' => '1',
        'number' => '130',
        'issue_date' => today()->toDateString(),
        'due_date' => today()->addDays(30)->toDateString(),
        'total_amount' => '600,00',
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '6,000',
                'unit_of_measure' => 'un',
                'unit_price' => '100,00',
                'line_total' => '600,00',
                'purchase_order_item_id' => $poItem->id,
            ],
        ],
    ]);

    $this->actingAs($user)->get(route('purchasing.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/orders/show')
            ->has('order.imputed_vouchers', 1)
            ->where('order.imputed_vouchers.0.quantity_received', '6.000')
            ->where('order.items.0.quantity_received', '6.000')
            ->where('order.items.0.quantity_pending', '4.000')
        );
});

test('imputing an item with non-matching article_id fails validation', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $articleA = Article::factory()->create(['description' => 'Artículo A']);
    $articleB = Article::factory()->create(['description' => 'Artículo B']);

    $order = PurchaseOrder::factory()->issued()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
    ]);
    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'article_id' => $articleA->id,
        'quantity' => '10.000',
    ]);

    // Send voucher item with articleB but referencing poItem with articleA
    $response = $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Invoice->value,
        'letter' => SupplierVoucherLetter::A->value,
        'point_of_sale' => '1',
        'number' => '131',
        'issue_date' => today()->toDateString(),
        'due_date' => today()->addDays(30)->toDateString(),
        'total_amount' => '100,00',
        'items' => [
            [
                'article_id' => $articleB->id,
                'description' => $articleB->description,
                'quantity' => '5,000',
                'unit_of_measure' => 'un',
                'unit_price' => '20,00',
                'line_total' => '100,00',
                'purchase_order_item_id' => $poItem->id,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('items.0.article_id');
});

test('imputing an item with zero pending balance fails validation', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $article = Article::factory()->create();

    $order = PurchaseOrder::factory()->issued()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
    ]);
    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'article_id' => $article->id,
        'quantity' => '10.000',
        'unit_price' => '100.00',
        'line_total' => '1000.00',
    ]);

    // Fulfill the order first
    $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Invoice->value,
        'letter' => SupplierVoucherLetter::A->value,
        'point_of_sale' => '1',
        'number' => '132',
        'issue_date' => today()->toDateString(),
        'due_date' => today()->addDays(30)->toDateString(),
        'total_amount' => '1000,00',
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '10,000',
                'unit_of_measure' => 'un',
                'unit_price' => '100,00',
                'line_total' => '1000,00',
                'purchase_order_item_id' => $poItem->id,
            ],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Fulfilled);

    // Attempt to impute again to the already fulfilled line
    $response = $this->actingAs($user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $supplier->id,
        'type' => SupplierVoucherType::Invoice->value,
        'letter' => SupplierVoucherLetter::A->value,
        'point_of_sale' => '1',
        'number' => '133',
        'issue_date' => today()->toDateString(),
        'due_date' => today()->addDays(30)->toDateString(),
        'total_amount' => '200,00',
        'items' => [
            [
                'article_id' => $article->id,
                'description' => $article->description,
                'quantity' => '2,000',
                'unit_of_measure' => 'un',
                'unit_price' => '100,00',
                'line_total' => '200,00',
                'purchase_order_item_id' => $poItem->id,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('items.0.purchase_order_item_id');
});
