<?php

use App\Actions\Ecommerce\StartWebOrderPreparation;
use App\Enums\Ecommerce\WebOrderStatus;
use App\Enums\Security\UserRole;
use App\Models\Ecommerce\WebOrder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->operator = User::factory()->create([
        'role' => UserRole::Deposito,
    ]);
    $this->action = app(StartWebOrderPreparation::class);
});

test('transitions a paid order to in_preparation recording timestamp and operator', function () {
    $order = WebOrder::factory()->paid()->create();

    $updated = $this->action->execute($order, $this->operator);

    expect($updated->status)->toBe(WebOrderStatus::InPreparation)
        ->and($updated->preparation_started_at)->not->toBeNull()
        ->and($updated->prepared_by)->toBe($this->operator->id);

    $this->assertDatabaseHas('web_orders', [
        'id' => $order->id,
        'status' => WebOrderStatus::InPreparation->value,
        'prepared_by' => $this->operator->id,
    ]);
});

test('rejects preparation if order is pending payment', function () {
    $order = WebOrder::factory()->create([
        'status' => WebOrderStatus::Pending,
    ]);

    expect(fn () => $this->action->execute($order, $this->operator))
        ->toThrow(ValidationException::class);

    expect($order->fresh()->status)->toBe(WebOrderStatus::Pending);
});

test('rejects preparation if order is already in_preparation', function () {
    $order = WebOrder::factory()->inPreparation()->create();

    expect(fn () => $this->action->execute($order, $this->operator))
        ->toThrow(ValidationException::class);
});

test('rejects preparation if order is already delivered', function () {
    $order = WebOrder::factory()->delivered()->create();

    expect(fn () => $this->action->execute($order, $this->operator))
        ->toThrow(ValidationException::class);
});
