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
                'name' => 'Entrada por Compra',
                'code' => StockMovementType::CODE_PURCHASE_ENTRY,
                'sign' => 1,
                'description' => 'Ingreso de mercadería por recepción de comprobante de proveedor',
            ],
            [
                'name' => 'Salida por Venta',
                'code' => StockMovementType::CODE_SALE_EXIT,
                'sign' => -1,
                'description' => 'Egreso de mercadería por confirmación de venta de mostrador o e-commerce',
            ],
            [
                'name' => 'Devolución de Cliente',
                'code' => StockMovementType::CODE_CUSTOMER_RETURN,
                'sign' => 1,
                'description' => 'Reingreso de mercadería por anulación o nota de crédito de venta',
            ],
            [
                'name' => 'Transferencia - Salida de Depósito',
                'code' => StockMovementType::CODE_WAREHOUSE_TRANSFER_OUT,
                'sign' => -1,
                'description' => 'Egreso del depósito de origen en una transferencia entre sucursales',
            ],
            [
                'name' => 'Transferencia - Entrada a Depósito',
                'code' => StockMovementType::CODE_WAREHOUSE_TRANSFER_IN,
                'sign' => 1,
                'description' => 'Ingreso al depósito de destino en una transferencia entre sucursales',
            ],
            [
                'name' => 'Carga Inicial de Inventario',
                'code' => StockMovementType::CODE_INITIAL_LOAD,
                'sign' => 1,
                'description' => 'Alta inicial de existencias para apertura y puesta en marcha del sistema',
            ],
            [
                'name' => 'Ajuste por Sobrante de Recuento',
                'code' => 'count_surplus',
                'sign' => 1,
                'description' => 'Diferencia favorable detectada en un recuento o auditoría física',
            ],
            [
                'name' => 'Ajuste por Faltante de Recuento',
                'code' => 'count_shortage',
                'sign' => -1,
                'description' => 'Diferencia negativa detectada en un recuento o auditoría física',
            ],
            [
                'name' => 'Merma por Rotura o Daño',
                'code' => 'breakage',
                'sign' => -1,
                'description' => 'Baja de mercadería averiada o deteriorada durante la manipulación',
            ],
            [
                'name' => 'Baja por Vencimiento',
                'code' => 'expiration',
                'sign' => -1,
                'description' => 'Baja de mercadería perecedera con fecha de vencimiento cumplida',
            ],
            [
                'name' => 'Baja por Pérdida o Robo',
                'code' => 'theft_loss',
                'sign' => -1,
                'description' => 'Baja por extravío no justificado o faltante por hurto en salón',
            ],
            [
                'name' => 'Devolución por Defecto de Fábrica',
                'code' => 'factory_defect',
                'sign' => -1,
                'description' => 'Mercadería no apta para la venta devuelta al proveedor por falla de origen',
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
