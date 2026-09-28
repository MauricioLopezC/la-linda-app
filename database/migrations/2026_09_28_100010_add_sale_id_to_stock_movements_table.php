<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Links the "Salida por Venta" movement to the sale that originated it (EPIC-06), same
     * pattern as supplier_voucher_id: nullable and unique, one movement per sale.
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('supplier_voucher_id')->unique()->constrained('sales')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropUnique(['sale_id']);
            $table->dropColumn('sale_id');
        });
    }
};
