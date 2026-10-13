<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $movementType = [
            'name' => 'Salida por Entrega de Pedido Web',
            'name_normalized' => 'salida por entrega de pedido web',
            'sign' => -1,
            'description' => 'Egreso de mercadería por entrega o despacho de pedido web desde depósito',
            'is_system' => true,
            'is_active' => true,
            'updated_at' => $now,
        ];

        $query = DB::table('stock_movement_types')
            ->where('code', 'web_order_delivery');

        if ($query->exists()) {
            $query->update($movementType);

            return;
        }

        DB::table('stock_movement_types')->insert([
            ...$movementType,
            'code' => 'web_order_delivery',
            'created_at' => $now,
        ]);
    }

    public function down(): void
    {
        $movementTypeId = DB::table('stock_movement_types')
            ->where('code', 'web_order_delivery')
            ->value('id');

        if ($movementTypeId === null) {
            return;
        }

        if (DB::table('stock_movements')->where('stock_movement_type_id', $movementTypeId)->exists()) {
            throw new RuntimeException(
                'No se puede revertir la migración: existen movimientos de salida por entrega de pedido web.'
            );
        }

        DB::table('stock_movement_types')->where('id', $movementTypeId)->delete();
    }
};
