<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gives every active online warehouse a point of sale (EPIC-15): online sales and their invoices
 * require one, and production runs migrations but not seeders. It takes the next free number
 * (3 with the seeded data) so online invoices do not share a point-of-sale number with the
 * counter ones. On an empty database it does nothing: PointOfSaleSeeder creates it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $warehouseIds = DB::table('warehouses')
            ->where('is_online_channel', true)
            ->where('is_active', true)
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('points_of_sale')
                ->whereColumn('points_of_sale.warehouse_id', 'warehouses.id'))
            ->orderBy('id')
            ->pluck('id');

        foreach ($warehouseIds as $warehouseId) {
            $now = now();

            DB::table('points_of_sale')->insert([
                'number' => (int) DB::table('points_of_sale')->max('number') + 1,
                'warehouse_id' => $warehouseId,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $pointOfSaleIds = DB::table('points_of_sale')
            ->join('warehouses', 'warehouses.id', '=', 'points_of_sale.warehouse_id')
            ->where('warehouses.is_online_channel', true)
            ->pluck('points_of_sale.id');

        if (
            DB::table('sales')->whereIn('point_of_sale_id', $pointOfSaleIds)->exists()
            || DB::table('invoices')->whereIn('point_of_sale_id', $pointOfSaleIds)->exists()
        ) {
            throw new RuntimeException(
                'No se puede revertir la migración: el punto de venta online ya tiene ventas o facturas.'
            );
        }

        DB::table('points_of_sale')->whereIn('id', $pointOfSaleIds)->delete();
    }
};
