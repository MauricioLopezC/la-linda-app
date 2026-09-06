<?php

use App\Enums\Purchasing\PurchaseOrderStatus;
use App\Models\Catalog\Article;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Models\Purchasing\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guest cannot access purchase order routes', function () {
    $this->get(route('purchasing.orders.index'))->assertRedirect(route('login'));
    $this->get(route('purchasing.orders.create'))->assertRedirect(route('login'));
    $this->post(route('purchasing.orders.store'), [])->assertRedirect(route('login'));
});

test('user can view purchase orders list and filter by supplier, warehouse, status, and dates', function () {
    $user = User::factory()->create();

    $supplierA = Supplier::factory()->create(['business_name' => 'Distribuidora Norte']);
    $supplierB = Supplier::factory()->create(['business_name' => 'Lácteos del Sur']);

    $warehouseA = Warehouse::factory()->create(['name' => 'Depósito Central']);
    $warehouseB = Warehouse::factory()->create(['name' => 'Depósito Secundario']);

    $order1 = PurchaseOrder::factory()->create([
        'supplier_id' => $supplierA->id,
        'warehouse_id' => $warehouseA->id,
        'order_number' => 'OC-000001',
        'status' => PurchaseOrderStatus::Draft,
        'issue_date' => '2026-09-01',
    ]);

    $order2 = PurchaseOrder::factory()->issued()->create([
        'supplier_id' => $supplierB->id,
        'warehouse_id' => $warehouseB->id,
        'order_number' => 'OC-000002',
        'issue_date' => '2026-09-03',
    ]);

    $order3 = PurchaseOrder::factory()->cancelled()->create([
        'supplier_id' => $supplierA->id,
        'warehouse_id' => $warehouseB->id,
        'order_number' => 'OC-000003',
        'issue_date' => '2026-09-05',
    ]);

    // List all
    $this->actingAs($user)->get(route('purchasing.orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/orders/index')
            ->has('orders.data', 3)
            ->has('suppliers')
            ->has('warehouses')
            ->has('statuses')
        );

    // Filter by supplier
    $this->actingAs($user)->get(route('purchasing.orders.index', ['supplier_id' => $supplierB->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/orders/index')
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', 'OC-000002')
        );

    // Filter by warehouse
    $this->actingAs($user)->get(route('purchasing.orders.index', ['warehouse_id' => $warehouseA->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/orders/index')
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', 'OC-000001')
        );

    // Filter by status
    $this->actingAs($user)->get(route('purchasing.orders.index', ['status' => 'cancelada']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/orders/index')
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', 'OC-000003')
        );

    // Filter by date range
    $this->actingAs($user)->get(route('purchasing.orders.index', ['date_from' => '2026-09-02', 'date_to' => '2026-09-04']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/orders/index')
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', 'OC-000002')
        );

    // Search filter
    $this->actingAs($user)->get(route('purchasing.orders.index', ['search' => 'OC-000001']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/orders/index')
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', 'OC-000001')
        );
});

test('user can view create form with active suppliers, warehouses and articles', function () {
    $user = User::factory()->create();

    $activeSupplier = Supplier::factory()->create(['is_active' => true]);
    $inactiveSupplier = Supplier::factory()->create(['is_active' => false]);

    $activeWarehouse = Warehouse::factory()->create(['is_active' => true]);
    $inactiveWarehouse = Warehouse::factory()->create(['is_active' => false]);

    $activeArticle = Article::factory()->create();

    $this->actingAs($user)->get(route('purchasing.orders.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/orders/form')
            ->has('suppliers', 1)
            ->where('suppliers.0.id', $activeSupplier->id)
            ->has('warehouses', 1)
            ->where('warehouses.0.id', $activeWarehouse->id)
            ->has('articles', 1)
            ->where('articles.0.id', $activeArticle->id)
        );
});

test('user can create a purchase order in draft status with valid articles and correct totals', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);

    $article1 = Article::factory()->create(['description' => 'Arroz 1kg']);
    $article2 = Article::factory()->create(['description' => 'Fideos 500g']);

    $response = $this->actingAs($user)->post(route('purchasing.orders.store'), [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'payment_terms' => '30 días fecha factura',
        'issue_date' => '2026-09-06',
        'expected_delivery_date' => '2026-09-12',
        'notes' => 'Entregar por la mañana.',
        'status' => 'borrador',
        'items' => [
            [
                'article_id' => $article1->id,
                'quantity' => '10.000',
                'unit_price' => '1500.50',
            ],
            [
                'article_id' => $article2->id,
                'quantity' => '5.000',
                'unit_price' => '800.25',
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $order = PurchaseOrder::firstOrFail();
    $response->assertRedirect(route('purchasing.orders.show', $order));

    expect($order->status)->toBe(PurchaseOrderStatus::Draft);
    expect($order->supplier_id)->toBe($supplier->id);
    expect($order->warehouse_id)->toBe($warehouse->id);
    expect($order->order_number)->toBe('OC-000001');

    // 10 * 1500.50 = 15005.00 ; 5 * 800.25 = 4001.25 ; Total = 19006.25
    expect((string) $order->total_amount)->toBe('19006.25');

    expect($order->items)->toHaveCount(2);

    $this->assertDatabaseHas('purchase_order_items', [
        'purchase_order_id' => $order->id,
        'article_id' => $article1->id,
        'quantity' => '10.000',
        'unit_price' => '1500.50',
        'line_total' => '15005.00',
    ]);

    $this->assertDatabaseHas('purchase_order_items', [
        'purchase_order_id' => $order->id,
        'article_id' => $article2->id,
        'quantity' => '5.000',
        'unit_price' => '800.25',
        'line_total' => '4001.25',
    ]);
});

test('user can create and emit directly if articles are present', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    $article = Article::factory()->create();

    $this->actingAs($user)->post(route('purchasing.orders.store'), [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'issue_date' => '2026-09-06',
        'status' => 'emitida',
        'items' => [
            [
                'article_id' => $article->id,
                'quantity' => '2.000',
                'unit_price' => '500.00',
            ],
        ],
    ])->assertSessionHasNoErrors();

    $order = PurchaseOrder::firstOrFail();
    expect($order->status)->toBe(PurchaseOrderStatus::Issued);
    expect((string) $order->total_amount)->toBe('1000.00');
});

test('cannot emit a purchase order without articles', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);

    $this->actingAs($user)->post(route('purchasing.orders.store'), [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'issue_date' => '2026-09-06',
        'status' => 'emitida',
        'items' => [],
    ])->assertSessionHasErrors(['items']);
});

test('purchase order creation rejects duplicate articles in the same order', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    $article = Article::factory()->create();

    $this->actingAs($user)->post(route('purchasing.orders.store'), [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'issue_date' => '2026-09-06',
        'status' => 'borrador',
        'items' => [
            ['article_id' => $article->id, 'quantity' => '1', 'unit_price' => '100'],
            ['article_id' => $article->id, 'quantity' => '2', 'unit_price' => '100'],
        ],
    ])->assertSessionHasErrors();
});

test('purchase order creation rejects zero or negative quantity and unit price', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    $article = Article::factory()->create();

    $this->actingAs($user)->post(route('purchasing.orders.store'), [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'issue_date' => '2026-09-06',
        'items' => [
            ['article_id' => $article->id, 'quantity' => '0', 'unit_price' => '100'],
        ],
    ])->assertSessionHasErrors(['items.0.quantity']);

    $this->actingAs($user)->post(route('purchasing.orders.store'), [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'issue_date' => '2026-09-06',
        'items' => [
            ['article_id' => $article->id, 'quantity' => '5', 'unit_price' => '-10'],
        ],
    ])->assertSessionHasErrors(['items.0.unit_price']);
});

test('purchase order creation rejects inactive supplier or inactive warehouse', function () {
    $user = User::factory()->create();
    $activeSupplier = Supplier::factory()->create(['is_active' => true]);
    $inactiveSupplier = Supplier::factory()->create(['is_active' => false]);
    $activeWarehouse = Warehouse::factory()->create(['is_active' => true]);
    $inactiveWarehouse = Warehouse::factory()->create(['is_active' => false]);
    $article = Article::factory()->create();

    // Inactive supplier
    $this->actingAs($user)->post(route('purchasing.orders.store'), [
        'supplier_id' => $inactiveSupplier->id,
        'warehouse_id' => $activeWarehouse->id,
        'issue_date' => '2026-09-06',
        'items' => [['article_id' => $article->id, 'quantity' => '1', 'unit_price' => '100']],
    ])->assertSessionHasErrors(['supplier_id']);

    // Inactive warehouse
    $this->actingAs($user)->post(route('purchasing.orders.store'), [
        'supplier_id' => $activeSupplier->id,
        'warehouse_id' => $inactiveWarehouse->id,
        'issue_date' => '2026-09-06',
        'items' => [['article_id' => $article->id, 'quantity' => '1', 'unit_price' => '100']],
    ])->assertSessionHasErrors(['warehouse_id']);
});

test('purchase order creation rejects expected delivery date before issue date', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    $article = Article::factory()->create();

    $this->actingAs($user)->post(route('purchasing.orders.store'), [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'issue_date' => '2026-09-10',
        'expected_delivery_date' => '2026-09-05',
        'items' => [['article_id' => $article->id, 'quantity' => '1', 'unit_price' => '100']],
    ])->assertSessionHasErrors(['expected_delivery_date']);
});

test('user can update a purchase order while in draft status', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    $article1 = Article::factory()->create();
    $article2 = Article::factory()->create();

    $order = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'status' => PurchaseOrderStatus::Draft,
        'issue_date' => '2026-09-01',
    ]);
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'article_id' => $article1->id,
        'quantity' => '2',
        'unit_price' => '100',
        'line_total' => '200',
    ]);
    $order->recalculateTotal();

    $this->actingAs($user)->put(route('purchasing.orders.update', $order), [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'issue_date' => '2026-09-02',
        'payment_terms' => 'Nuevo plazo 15 días',
        'status' => 'borrador',
        'items' => [
            ['article_id' => $article2->id, 'quantity' => '4.000', 'unit_price' => '300.00'],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect(route('purchasing.orders.show', $order));

    expect($order->fresh())
        ->payment_terms->toBe('Nuevo plazo 15 días')
        ->total_amount->toBe('1200.00');

    expect($order->items()->count())->toBe(1);
    expect($order->items()->first()->article_id)->toBe($article2->id);
});

test('user cannot update a purchase order once emitted or cancelled (immutability)', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    $article = Article::factory()->create();

    $issuedOrder = PurchaseOrder::factory()->issued()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $this->actingAs($user)->put(route('purchasing.orders.update', $issuedOrder), [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'issue_date' => '2026-09-02',
        'status' => 'borrador',
        'items' => [['article_id' => $article->id, 'quantity' => '1', 'unit_price' => '100']],
    ])->assertSessionHasErrors(['status']);

    $cancelledOrder = PurchaseOrder::factory()->cancelled()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $this->actingAs($user)->put(route('purchasing.orders.update', $cancelledOrder), [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'issue_date' => '2026-09-02',
        'status' => 'borrador',
        'items' => [['article_id' => $article->id, 'quantity' => '1', 'unit_price' => '100']],
    ])->assertSessionHasErrors(['status']);
});

test('user can emit a draft purchase order', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    $article = Article::factory()->create();

    $order = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'article_id' => $article->id,
        'quantity' => '5',
        'unit_price' => '100',
        'line_total' => '500',
    ]);
    $order->recalculateTotal();

    $this->actingAs($user)->post(route('purchasing.orders.issue', $order))
        ->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Issued);
});

test('user can cancel an emitted purchase order providing a valid reason', function () {
    $user = User::factory()->create();
    $order = PurchaseOrder::factory()->issued()->create();

    $this->actingAs($user)->post(route('purchasing.orders.cancel', $order), [
        'reason' => 'El proveedor informó falta de stock para la fecha solicitada.',
    ])->assertSessionHasNoErrors();

    $refreshed = $order->fresh();
    expect($refreshed->status)->toBe(PurchaseOrderStatus::Cancelled);
    expect($refreshed->cancellation_reason)->toBe('El proveedor informó falta de stock para la fecha solicitada.');
    expect($refreshed->cancelled_by)->toBe($user->id);
    expect($refreshed->cancelled_at)->not->toBeNull();
});

test('user cannot cancel an already cancelled purchase order', function () {
    $user = User::factory()->create();
    $order = PurchaseOrder::factory()->cancelled()->create();

    $this->actingAs($user)->post(route('purchasing.orders.cancel', $order), [
        'reason' => 'Intento de segunda cancelación',
    ])->assertSessionHasErrors(['status']);
});

test('emitting or cancelling a purchase order does not modify stock balances or stock movements', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    $article = Article::factory()->create();

    $initialBalances = StockBalance::count();
    $initialMovements = StockMovement::count();

    $order = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'article_id' => $article->id,
        'quantity' => '50',
        'unit_price' => '100',
        'line_total' => '5000',
    ]);

    // Emit
    $this->actingAs($user)->post(route('purchasing.orders.issue', $order))
        ->assertSessionHasNoErrors();

    expect(StockBalance::count())->toBe($initialBalances);
    expect(StockMovement::count())->toBe($initialMovements);

    // Cancel
    $this->actingAs($user)->post(route('purchasing.orders.cancel', $order), [
        'reason' => 'Cancelación de verificación de stock.',
    ])->assertSessionHasNoErrors();

    expect(StockBalance::count())->toBe($initialBalances);
    expect(StockMovement::count())->toBe($initialMovements);
});

test('user can download PDF of purchase order', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['business_name' => 'Proveedor PDF Test', 'tax_id' => '30500858628']);
    $warehouse = Warehouse::factory()->create(['name' => 'Depósito Principal']);
    $article = Article::factory()->create(['description' => 'Producto A']);

    $order = PurchaseOrder::factory()->issued()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_number' => 'OC-000099',
    ]);
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'article_id' => $article->id,
        'quantity' => '10',
        'unit_price' => '250',
        'line_total' => '2500',
    ]);
    $order->recalculateTotal();

    $response = $this->actingAs($user)->get(route('purchasing.orders.pdf', $order));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('orden-de-compra-OC-000099.pdf');
});

test('supplier with purchase orders cannot be physically deleted', function () {
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);

    PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $this->actingAs($user)->delete(route('purchasing.suppliers.destroy', $supplier))
        ->assertSessionHasErrors(['supplier']);

    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
});
