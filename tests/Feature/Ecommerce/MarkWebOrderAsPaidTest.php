<?php

use App\Actions\Ecommerce\MarkWebOrderAsPaid;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Models\Ecommerce\WebOrder;
use Carbon\Carbon;
use InvalidArgumentException;
use RuntimeException;

beforeEach(function () {
    $this->action = app(MarkWebOrderAsPaid::class);
});

test('marks pending order as paid with payment id amount and timestamp', function () {
    $order = WebOrder::factory()->create([
        'items_amount' => '3000.00',
        'shipping_cost' => '0.00',
        'total_amount' => '3000.00',
        'status' => WebOrderStatus::Pending,
        'paid_at' => null,
        'paid_amount' => null,
    ]);

    $paidAt = Carbon::parse('2026-10-08 14:30:00');
    $updated = $this->action->execute($order, 'pay-998877', '3000.00', $paidAt);

    expect($updated->status)->toBe(WebOrderStatus::Paid)
        ->and($updated->mp_payment_id)->toBe('pay-998877')
        ->and($updated->paid_amount)->toBe('3000.00')
        ->and($updated->paid_at->toDateTimeString())->toBe('2026-10-08 14:30:00');

    expect($order->fresh()->status)->toBe(WebOrderStatus::Paid)
        ->and($order->fresh()->paid_amount)->toBe('3000.00');
});

test('is idempotent when called multiple times with the same payment id', function () {
    $paidAt = Carbon::parse('2026-10-08 14:30:00');
    $order = WebOrder::factory()->paid()->create([
        'items_amount' => '5000.00',
        'shipping_cost' => '0.00',
        'total_amount' => '5000.00',
        'paid_amount' => '5000.00',
        'mp_payment_id' => 'pay-repeat-123',
        'paid_at' => $paidAt,
    ]);

    // Replay exact same notification
    $result = $this->action->execute($order, 'pay-repeat-123', '5000.00', Carbon::parse('2026-10-08 15:00:00'));

    expect($result->id)->toBe($order->id)
        ->and($result->status)->toBe(WebOrderStatus::Paid)
        ->and($result->mp_payment_id)->toBe('pay-repeat-123')
        ->and($result->paid_at->toDateTimeString())->toBe('2026-10-08 14:30:00');
});

test('rejects payment when paid amount does not match order total', function () {
    $order = WebOrder::factory()->create([
        'items_amount' => '3000.00',
        'shipping_cost' => '0.00',
        'total_amount' => '3000.00',
    ]);

    expect(fn () => $this->action->execute($order, 'pay-998877', '2999.00', now()))
        ->toThrow(InvalidArgumentException::class, 'no coincide con el total del pedido');

    expect($order->fresh()->status)->toBe(WebOrderStatus::Pending);
});

test('rejects paying order with a different payment id if already paid', function () {
    $order = WebOrder::factory()->paid()->create([
        'items_amount' => '3000.00',
        'shipping_cost' => '0.00',
        'total_amount' => '3000.00',
        'paid_amount' => '3000.00',
        'mp_payment_id' => 'pay-first-111',
    ]);

    expect(fn () => $this->action->execute($order, 'pay-second-222', '3000.00', now()))
        ->toThrow(RuntimeException::class, 'ya se encuentra pagado con otro pago de Mercado Pago');
});
