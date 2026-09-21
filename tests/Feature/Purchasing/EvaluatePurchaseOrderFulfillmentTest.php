<?php

use App\Actions\Purchasing\EvaluatePurchaseOrderFulfillment;
use App\Enums\Purchasing\PurchaseOrderStatus;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Models\Catalog\Article;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Models\Purchasing\PurchaseOrderVoucherImputation;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\SupplierVoucherItem;

function hu038Receive(PurchaseOrderItem $orderItem, SupplierVoucher $voucher, string $received, string $excess = '0.000'): void
{
    $voucherItem = SupplierVoucherItem::factory()->create([
        'supplier_voucher_id' => $voucher->id,
        'position' => $voucher->items()->count() + 1,
        'article_id' => $orderItem->article_id,
    ]);

    PurchaseOrderVoucherImputation::create([
        'purchase_order_item_id' => $orderItem->id,
        'supplier_voucher_item_id' => $voucherItem->id,
        'quantity_received' => $received,
        'quantity_excess' => $excess,
    ]);
}

test('an issued order closes only when every line is fully covered', function () {
    $supplier = Supplier::factory()->create();
    $order = PurchaseOrder::factory()->issued()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => Warehouse::factory()->create()->id,
    ]);
    $first = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'article_id' => Article::factory()->create()->id,
        'quantity' => '5.000',
    ]);
    $second = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'article_id' => Article::factory()->create()->id,
        'quantity' => '3.000',
    ]);
    $voucher = SupplierVoucher::factory()->invoice()->create(['supplier_id' => $supplier->id]);
    $evaluate = app(EvaluatePurchaseOrderFulfillment::class);

    hu038Receive($first, $voucher, '5.000');
    hu038Receive($second, $voucher, '2.000');
    $evaluate->handle($order);
    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Issued)
        ->and($second->quantityPending())->toBe('1.000');

    hu038Receive($second, $voucher, '1.000', '0.500');
    $evaluate->handleMany([$order->id]);
    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Fulfilled)
        ->and($second->quantityPending())->toBe('0.000');

    $voucher->update(['status' => SupplierVoucherStatus::Cancelled]);
    $evaluate->handle($order);
    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Issued)
        ->and($second->quantityPending())->toBe('3.000');
});

test('annulling one of several partial receipts leaves the order issued with its remaining balance', function () {
    $supplier = Supplier::factory()->create();
    $order = PurchaseOrder::factory()->issued()->create(['supplier_id' => $supplier->id]);
    $item = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'quantity' => '10.000',
    ]);
    $first = SupplierVoucher::factory()->invoice()->create(['supplier_id' => $supplier->id]);
    $second = SupplierVoucher::factory()->invoice()->create(['supplier_id' => $supplier->id]);
    hu038Receive($item, $first, '4.000');
    hu038Receive($item, $second, '3.000');

    $first->update(['status' => SupplierVoucherStatus::Cancelled]);
    app(EvaluatePurchaseOrderFulfillment::class)->handle($order);

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Issued)
        ->and($item->quantityPending())->toBe('7.000');
});

test('draft and cancelled orders are not transitioned by fulfillment evaluation', function (PurchaseOrderStatus $status) {
    $order = PurchaseOrder::factory()->create(['status' => $status]);
    $item = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'quantity' => '1.000',
    ]);
    $voucher = SupplierVoucher::factory()->invoice()->create(['supplier_id' => $order->supplier_id]);
    hu038Receive($item, $voucher, '1.000');

    app(EvaluatePurchaseOrderFulfillment::class)->handle($order);

    expect($order->fresh()->status)->toBe($status);
})->with([PurchaseOrderStatus::Draft, PurchaseOrderStatus::Cancelled]);
