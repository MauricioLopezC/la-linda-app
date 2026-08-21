<?php

namespace Database\Seeders\Sales;

use App\Models\Sales\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PaymentMethod::unguarded(function (): void {
            foreach ([
                ['name' => 'Efectivo', 'is_enabled_online' => false],
                ['name' => 'Tarjeta de Débito', 'is_enabled_online' => false],
                ['name' => 'Tarjeta de Crédito', 'is_enabled_online' => true],
                ['name' => 'Transferencia Bancaria', 'is_enabled_online' => true],
                ['name' => 'Mercado Pago', 'is_enabled_online' => true],
            ] as $paymentMethod) {
                PaymentMethod::firstOrCreate(
                    ['name_normalized' => PaymentMethod::normalizeUniqueValue($paymentMethod['name'])],
                    ['name' => $paymentMethod['name'], 'is_enabled_online' => $paymentMethod['is_enabled_online'], 'is_active' => true],
                );
            }
        });
    }
}
