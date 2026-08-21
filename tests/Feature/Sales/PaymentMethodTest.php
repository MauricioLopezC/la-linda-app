<?php

use App\Models\Sales\PaymentMethod;
use App\Models\User;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

test('guest cannot access payment method management', function () {
    $this->get(route('sales.payment-methods.index'))->assertRedirect(route('login'));
});

test('user can view payment method management', function () {
    $user = User::factory()->create();
    PaymentMethod::factory()->count(2)->create();

    $this->actingAs($user)->get(route('sales.payment-methods.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('sales/payment-methods/index')->has('paymentMethods', 2));
});

test('user can create and update a payment method', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('sales.payment-methods.store'), [
        'name' => '  Efectivo ',
        'is_enabled_online' => false,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $paymentMethod = PaymentMethod::firstOrFail();
    expect($paymentMethod->name)->toBe('Efectivo');
    expect($paymentMethod->is_enabled_online)->toBeFalse();

    $this->actingAs($user)->put(route('sales.payment-methods.update', $paymentMethod), [
        'name' => 'Efectivo en caja',
        'is_enabled_online' => true,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect($paymentMethod->fresh())
        ->name->toBe('Efectivo en caja')
        ->is_enabled_online->toBeTrue();
});

test('payment method name is unique ignoring case and outer spaces', function () {
    $user = User::factory()->create();
    PaymentMethod::factory()->create(['name' => 'Mercado Pago']);

    $this->actingAs($user)->post(route('sales.payment-methods.store'), [
        'name' => '  MERCADO PAGO ',
    ])->assertSessionHasErrors(['name']);
});

test('database index protects normalized payment method name uniqueness', function () {
    PaymentMethod::factory()->create(['name' => 'Mercado Pago']);

    expect(fn () => PaymentMethod::factory()->create(['name' => ' MERCADO PAGO ']))
        ->toThrow(QueryException::class);
});

test('payment method status can be toggled while sales usage is not implemented', function () {
    $user = User::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();

    $this->actingAs($user)->patch(route('sales.payment-methods.toggle', $paymentMethod))
        ->assertSessionHasNoErrors();

    expect($paymentMethod->fresh()->is_active)->toBeFalse();
});
