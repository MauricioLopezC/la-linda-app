<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Existing invoices can only recover the current POS number: earlier numbers were
     * not stored. Nullable allows adding the column to populated SQLite tables; issuance
     * always supplies the snapshot after this backfill.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedInteger('point_of_sale_number')->nullable();
        });

        DB::table('invoices')->whereNull('point_of_sale_number')->update([
            'point_of_sale_number' => DB::table('points_of_sale')
                ->select('number')
                ->whereColumn('points_of_sale.id', 'invoices.point_of_sale_id'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('point_of_sale_number');
        });
    }
};
