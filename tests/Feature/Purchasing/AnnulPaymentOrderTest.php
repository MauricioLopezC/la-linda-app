<?php

namespace Tests\Feature\Purchasing;

use App\Actions\Purchasing\AnnulPaymentOrder;
use App\Enums\Purchasing\PaymentOrderStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Purchasing\PaymentOrder;
use App\Models\Purchasing\PaymentOrderItem;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('annuls a payment order and restores invoice balances', function () {
    $user = User::factory()->create();

    // Create an invoice with total 1000 and 1000 applied (so 0 pending)
    $invoice = SupplierVoucher::factory()->create([
        'type' => SupplierVoucherType::Invoice,
        'total_amount' => '1000.00',
    ]);

    $order = PaymentOrder::factory()->create([
        'status' => PaymentOrderStatus::Issued,
        'total_amount' => '1000.00',
    ]);

    PaymentOrderItem::factory()->create([
        'payment_order_id' => $order->id,
        'supplier_voucher_id' => $invoice->id,
        'amount_applied' => '1000.00',
    ]);

    expect($invoice->pendingBalance())->toBe('0.00');

    $action = app(AnnulPaymentOrder::class);
    $action->handle($order, $user->id);

    expect($order->fresh()->status)->toBe(PaymentOrderStatus::Cancelled);
    // Invoice should now have 1000 pending because the OP is cancelled
    expect($invoice->fresh()->pendingBalance())->toBe('1000.00');
});

test('it prevents annulling an already cancelled order', function () {
    $user = User::factory()->create();

    $order = PaymentOrder::factory()->create([
        'status' => PaymentOrderStatus::Cancelled,
    ]);

    $action = app(AnnulPaymentOrder::class);

    expect(fn () => $action->handle($order, $user->id))
        ->toThrow(ValidationException::class, 'La orden de pago ya se encuentra anulada.');
});

test('it exposes a destroy endpoint to annul the order', function () {
    $user = User::factory()->create();
    $order = PaymentOrder::factory()->create([
        'status' => PaymentOrderStatus::Issued,
    ]);

    actingAs($user)
        ->delete(route('purchasing.payment-orders.destroy', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($order->fresh()->status)->toBe(PaymentOrderStatus::Cancelled);
});
