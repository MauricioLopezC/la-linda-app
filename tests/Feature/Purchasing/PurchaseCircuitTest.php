<?php

use App\Enums\Purchasing\PurchaseOrderStatus;
use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Catalog\Article;
use App\Models\Catalog\ArticleSupplier;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\User;
use Database\Seeders\Inventory\StockMovementTypeSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(StockMovementTypeSeeder::class);

    $this->user = User::factory()->create();
    $this->supplier = Supplier::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->article = Article::factory()->create();
    ArticleSupplier::factory()->create(['article_id' => $this->article->id, 'supplier_id' => $this->supplier->id]);
    $this->order = PurchaseOrder::factory()->issued()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
    ]);
    $this->orderItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $this->order->id,
        'article_id' => $this->article->id,
        'quantity' => '10.000',
        'unit_price' => '100.00',
        'line_total' => '1000.00',
    ]);
});

/**
 * Registers a supplier voucher of the given type through HTTP, imputing one line to an order item.
 */
function postCircuitVoucher(SupplierVoucherType $type, string $number, string $quantity, ?int $orderItemId): TestResponse
{
    $test = test();
    $isRemito = $type === SupplierVoucherType::Remito;

    return $test->actingAs($test->user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $test->supplier->id,
        'type' => $type->value,
        'letter' => $isRemito ? SupplierVoucherLetter::R->value : SupplierVoucherLetter::A->value,
        'point_of_sale' => '1',
        'number' => $number,
        'issue_date' => today()->toDateString(),
        'due_date' => $isRemito ? null : today()->addDays(30)->toDateString(),
        'total_amount' => $isRemito ? '' : '1.000,00',
        'items' => [
            [
                'article_id' => $test->article->id,
                'description' => $test->article->description,
                'quantity' => $quantity,
                'unit_of_measure' => 'un',
                'unit_price' => $isRemito ? '' : '100,00',
                'line_total' => $isRemito ? '' : '1.000,00',
                'purchase_order_item_id' => $orderItemId,
            ],
        ],
    ]);
}

function circuitStock(): string
{
    $test = test();

    return (string) (StockBalance::query()
        ->where('article_id', $test->article->id)
        ->where('warehouse_id', $test->warehouse->id)
        ->value('quantity') ?? '0.000');
}

test('order → invoice → remito: the invoice does not block the remito and the order closes after both', function () {
    postCircuitVoucher(SupplierVoucherType::Invoice, '1', '10', $this->orderItem->id)
        ->assertSessionHasNoErrors()->assertRedirect();

    expect($this->order->fresh()->status)->toBe(PurchaseOrderStatus::Issued)
        ->and($this->orderItem->quantityInvoiced())->toBe('10.000')
        ->and($this->orderItem->quantityPendingToReceive())->toBe('10.000')
        ->and(circuitStock())->toBe('0.000');

    postCircuitVoucher(SupplierVoucherType::Remito, '2', '10', $this->orderItem->id)
        ->assertSessionHasNoErrors()->assertRedirect();

    expect($this->order->fresh()->status)->toBe(PurchaseOrderStatus::Fulfilled)
        ->and($this->orderItem->quantityReceived())->toBe('10.000')
        ->and(circuitStock())->toBe('10.000');
});

test('order → remito → invoice: the remito enters stock and the invoice closes the order', function () {
    postCircuitVoucher(SupplierVoucherType::Remito, '1', '10', $this->orderItem->id)
        ->assertSessionHasNoErrors()->assertRedirect();

    expect($this->order->fresh()->status)->toBe(PurchaseOrderStatus::Issued)
        ->and(circuitStock())->toBe('10.000');

    postCircuitVoucher(SupplierVoucherType::Invoice, '2', '10', $this->orderItem->id)
        ->assertSessionHasNoErrors()->assertRedirect();

    expect($this->order->fresh()->status)->toBe(PurchaseOrderStatus::Fulfilled)
        ->and(circuitStock())->toBe('10.000');
});

test('each track rejects covering the same line twice', function (SupplierVoucherType $type, string $coverage) {
    postCircuitVoucher($type, '1', '10', $this->orderItem->id)->assertSessionHasNoErrors();

    postCircuitVoucher($type, '2', '1', $this->orderItem->id)->assertSessionHasErrors([
        'items.0.purchase_order_item_id' => "El renglón de la orden de compra #{$this->order->order_number} ya se encuentra {$coverage} en su totalidad.",
    ]);
})->with([
    'invoice' => [SupplierVoucherType::Invoice, 'facturado'],
    'remito' => [SupplierVoucherType::Remito, 'recibido'],
]);

test('annulling the remito of a fulfilled order reopens it and reverses the stock', function () {
    postCircuitVoucher(SupplierVoucherType::Invoice, '1', '10', $this->orderItem->id)->assertSessionHasNoErrors();
    postCircuitVoucher(SupplierVoucherType::Remito, '2', '10', $this->orderItem->id)->assertSessionHasNoErrors();
    $remito = SupplierVoucher::query()->where('type', SupplierVoucherType::Remito)->firstOrFail();

    $this->actingAs($this->user)
        ->post(route('purchasing.vouchers.annul', $remito), ['reason' => 'Mercadería devuelta'])
        ->assertRedirect();

    expect($this->order->fresh()->status)->toBe(PurchaseOrderStatus::Issued)
        ->and($this->orderItem->quantityPendingToReceive())->toBe('10.000')
        ->and($this->orderItem->quantityInvoiced())->toBe('10.000')
        ->and(circuitStock())->toBe('0.000');
});

test('credit and debit notes cannot be imputed to a purchase order', function (SupplierVoucherType $type) {
    $this->actingAs($this->user)->post(route('purchasing.vouchers.store'), [
        'supplier_id' => $this->supplier->id,
        'type' => $type->value,
        'letter' => SupplierVoucherLetter::A->value,
        'point_of_sale' => '1',
        'number' => '1',
        'issue_date' => today()->toDateString(),
        'total_amount' => '100,00',
        'items' => [
            [
                'article_id' => $this->article->id,
                'description' => $this->article->description,
                'quantity' => '1',
                'unit_of_measure' => 'un',
                'unit_price' => '100,00',
                'line_total' => '100,00',
                'purchase_order_item_id' => $this->orderItem->id,
            ],
        ],
    ])->assertSessionHasErrors([
        'items.0.purchase_order_item_id' => 'Solo las facturas y los remitos pueden imputarse a una orden de compra.',
    ]);

    expect(SupplierVoucher::query()->count())->toBe(0)
        ->and($this->orderItem->imputations()->count())->toBe(0);
})->with([SupplierVoucherType::DebitNote, SupplierVoucherType::CreditNote]);

test('associable orders are filtered by the pending quantity of the requested voucher type', function () {
    postCircuitVoucher(SupplierVoucherType::Invoice, '1', '10', $this->orderItem->id)->assertSessionHasNoErrors();

    $associable = fn (SupplierVoucherType $type): TestResponse => $this->actingAs($this->user)->getJson(
        route('purchasing.vouchers.associable-purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'type' => $type->value,
        ])
    );

    $associable(SupplierVoucherType::Invoice)->assertOk()->assertJsonCount(0);
    $associable(SupplierVoucherType::Remito)->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.items.0.quantity_covered', '0.000')
        ->assertJsonPath('0.items.0.quantity_pending', '10.000');
});

test('associable orders require an imputable voucher type', function (?string $type) {
    $this->actingAs($this->user)
        ->getJson(route('purchasing.vouchers.associable-purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'type' => $type,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('type');
})->with([null, SupplierVoucherType::DebitNote->value, SupplierVoucherType::CreditNote->value]);
