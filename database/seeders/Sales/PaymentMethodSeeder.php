<?php

namespace Database\Seeders\Sales;

use App\Enums\Sales\PaymentMethodKind;
use App\Models\Sales\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
            ['name' => 'Efectivo', 'kind' => PaymentMethodKind::Cash, 'is_enabled_online' => false],
            ['name' => 'Tarjeta de Débito', 'kind' => PaymentMethodKind::Card, 'is_enabled_online' => false],
            ['name' => 'Tarjeta de Crédito', 'kind' => PaymentMethodKind::Card, 'is_enabled_online' => true],
            ['name' => 'Transferencia Bancaria', 'kind' => PaymentMethodKind::Transfer, 'is_enabled_online' => true],
            ['name' => 'Mercado Pago', 'kind' => PaymentMethodKind::VirtualWallet, 'is_enabled_online' => true],
            ['name' => 'Cheque de Pago Diferido', 'kind' => PaymentMethodKind::Other, 'is_enabled_online' => false],
        ];

        PaymentMethod::unguarded(function () use ($paymentMethods): void {
            foreach ($paymentMethods as $paymentMethod) {
                PaymentMethod::updateOrCreate(
                    ['name_normalized' => PaymentMethod::normalizeUniqueValue($paymentMethod['name'])],
                    [
                        'name' => $paymentMethod['name'],
                        'kind' => $paymentMethod['kind'],
                        'is_enabled_online' => $paymentMethod['is_enabled_online'],
                        'is_active' => true,
                    ]
                );
            }
        });
    }
}
