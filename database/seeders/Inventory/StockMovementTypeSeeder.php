<?php

namespace Database\Seeders\Inventory;

use App\Models\Inventory\StockMovementType;
use Illuminate\Database\Seeder;

class StockMovementTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'Entrada por compra',
                'code' => StockMovementType::CODE_PURCHASE_ENTRY,
                'sign' => 1,
                'description' => 'Ingreso de mercadería por recepción de comprobante de proveedor',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Salida por venta',
                'code' => StockMovementType::CODE_SALE_EXIT,
                'sign' => -1,
                'description' => 'Egreso de mercadería por confirmación de venta',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Devolución de cliente',
                'code' => StockMovementType::CODE_CUSTOMER_RETURN,
                'sign' => 1,
                'description' => 'Reingreso de mercadería por anulación o devolución de venta',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Transferencia entre depósitos',
                'code' => StockMovementType::CODE_WAREHOUSE_TRANSFER,
                'sign' => 1,
                'description' => 'Movimiento de mercadería entre depósitos del sistema',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Ajuste de inventario',
                'code' => StockMovementType::CODE_INVENTORY_ADJUSTMENT,
                'sign' => 1,
                'description' => 'Ajuste de existencias por diferencia física documentada',
                'is_system' => true,
                'is_active' => true,
            ],
        ];

        StockMovementType::unguarded(function () use ($types): void {
            foreach ($types as $type) {
                StockMovementType::updateOrCreate(
                    ['code' => $type['code']],
                    [
                        ...$type,
                        'name_normalized' => StockMovementType::normalizeUniqueValue($type['name']),
                    ]
                );
            }
        });
    }
}
