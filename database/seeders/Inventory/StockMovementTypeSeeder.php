<?php

namespace Database\Seeders\Inventory;

use App\Models\Inventory\StockMovementType;
use Illuminate\Database\Seeder;

class StockMovementTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Every type carries a fixed sign (+1 enters, -1 leaves). The manual stock-movement screen
     * only offers the non-automatic ones; the descriptive name is the movement's justification in
     * the history, so this list doubles as the seed catalog the admin can extend in HU-031.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'Entrada por compra',
                'code' => StockMovementType::CODE_PURCHASE_ENTRY,
                'sign' => 1,
                'description' => 'Ingreso de mercadería por recepción de comprobante de proveedor',
            ],
            [
                'name' => 'Salida por venta',
                'code' => StockMovementType::CODE_SALE_EXIT,
                'sign' => -1,
                'description' => 'Egreso de mercadería por confirmación de venta',
            ],
            [
                'name' => 'Devolución de cliente',
                'code' => StockMovementType::CODE_CUSTOMER_RETURN,
                'sign' => 1,
                'description' => 'Reingreso de mercadería por anulación o devolución de venta',
            ],
            [
                'name' => 'Transferencia - salida',
                'code' => StockMovementType::CODE_WAREHOUSE_TRANSFER_OUT,
                'sign' => -1,
                'description' => 'Egreso del depósito de origen en una transferencia entre depósitos',
            ],
            [
                'name' => 'Transferencia - entrada',
                'code' => StockMovementType::CODE_WAREHOUSE_TRANSFER_IN,
                'sign' => 1,
                'description' => 'Ingreso al depósito de destino en una transferencia entre depósitos',
            ],
            [
                'name' => 'Carga inicial de inventario',
                'code' => StockMovementType::CODE_INITIAL_LOAD,
                'sign' => 1,
                'description' => 'Alta inicial de existencias al poner en marcha el sistema',
            ],
            [
                'name' => 'Ajuste por sobrante de recuento',
                'code' => 'count_surplus',
                'sign' => 1,
                'description' => 'Diferencia positiva detectada en un recuento físico',
            ],
            [
                'name' => 'Ajuste por faltante de recuento',
                'code' => 'count_shortage',
                'sign' => -1,
                'description' => 'Diferencia negativa detectada en un recuento físico',
            ],
            [
                'name' => 'Merma / Rotura',
                'code' => 'breakage',
                'sign' => -1,
                'description' => 'Mercadería dañada en depósito o manipulación',
            ],
            [
                'name' => 'Vencimiento',
                'code' => 'expiration',
                'sign' => -1,
                'description' => 'Mercadería perecedera vencida dada de baja',
            ],
            [
                'name' => 'Robo / Pérdida',
                'code' => 'theft_loss',
                'sign' => -1,
                'description' => 'Pérdida no justificada de mercadería',
            ],
            [
                'name' => 'Defecto de fábrica',
                'code' => 'factory_defect',
                'sign' => -1,
                'description' => 'Mercadería no apta para la venta por falla de origen',
            ],
        ];

        StockMovementType::unguarded(function () use ($types): void {
            foreach ($types as $type) {
                StockMovementType::updateOrCreate(
                    ['code' => $type['code']],
                    [
                        ...$type,
                        'name_normalized' => StockMovementType::normalizeUniqueValue($type['name']),
                        'is_system' => true,
                        'is_active' => true,
                    ]
                );
            }
        });
    }
}
