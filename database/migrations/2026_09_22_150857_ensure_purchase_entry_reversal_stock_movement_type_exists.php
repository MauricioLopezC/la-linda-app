<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $movementType = [
            'name' => 'Reversión de Entrada por Compra',
            'name_normalized' => 'reversión de entrada por compra',
            'sign' => -1,
            'description' => 'Reversión de mercadería por anulación de remito de proveedor',
            'is_system' => true,
            'is_active' => true,
            'updated_at' => $now,
        ];

        $query = DB::table('stock_movement_types')
            ->where('code', 'purchase_entry_reversal');

        if ($query->exists()) {
            $query->update($movementType);

            return;
        }

        DB::table('stock_movement_types')->insert([
            ...$movementType,
            'code' => 'purchase_entry_reversal',
            'created_at' => $now,
        ]);
    }

    public function down(): void
    {
        $movementTypeId = DB::table('stock_movement_types')
            ->where('code', 'purchase_entry_reversal')
            ->value('id');

        if ($movementTypeId === null) {
            return;
        }

        if (DB::table('stock_movements')->where('stock_movement_type_id', $movementTypeId)->exists()) {
            throw new RuntimeException(
                'No se puede revertir la migración: existen movimientos de reversión de entradas por compra.'
            );
        }

        DB::table('stock_movement_types')->where('id', $movementTypeId)->delete();
    }
};
