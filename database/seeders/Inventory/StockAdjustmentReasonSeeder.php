<?php

namespace Database\Seeders\Inventory;

use App\Models\Inventory\StockAdjustmentReason;
use Illuminate\Database\Seeder;

class StockAdjustmentReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reasons = [
            [
                'name' => 'Rotura / Daño',
                'description' => 'Mercadería dañada en depósito o manipulación',
                'is_active' => true,
            ],
            [
                'name' => 'Vencimiento',
                'description' => 'Mercadería perecedera vencida',
                'is_active' => true,
            ],
            [
                'name' => 'Faltante de inventario',
                'description' => 'Diferencia negativa detectada en recuento físico',
                'is_active' => true,
            ],
            [
                'name' => 'Sobrante de inventario',
                'description' => 'Diferencia positiva detectada en recuento físico',
                'is_active' => true,
            ],
            [
                'name' => 'Defecto de fábrica',
                'description' => 'Mercadería no apta para la venta por falla de origen',
                'is_active' => true,
            ],
            [
                'name' => 'Robo / Pérdida',
                'description' => 'Pérdida no justificada de mercadería',
                'is_active' => true,
            ],
        ];

        foreach ($reasons as $reason) {
            StockAdjustmentReason::firstOrCreate(
                ['name' => $reason['name']],
                $reason
            );
        }
    }
}
